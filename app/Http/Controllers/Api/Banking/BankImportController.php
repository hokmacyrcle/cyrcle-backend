<?php

namespace App\Http\Controllers\Api\Banking;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\BankTransaction;
use App\Models\BusinessBankAccount;
use App\Services\Banking\StatementParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Statement import: look at the file first (preview), then import it with the chosen columns. */
class BankImportController extends Controller
{
    use SalesSupport;

    private const MAX_LINES = 20000;

    private function account($b, $id): ?BusinessBankAccount
    {
        return BusinessBankAccount::where('company_id', $b->company_id)->where('active', true)->find($id);
    }

    /** @return string|JsonResponse the file text as UTF-8, or an error */
    private function readFile(Request $request)
    {
        $v = Validator::make($request->all(), ['file' => ['required', 'file', 'max:5120']],
            ['file.required' => 'Choose a statement file.', 'file.max' => 'That file is over 5 MB. Export a shorter date range.']);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $text = (string) file_get_contents($request->file('file')->getRealPath());
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');     // older bank exports
        }
        if (str_contains(substr($text, 0, 2000), "\0") || preg_match('/^%PDF/', $text)) {
            return $this->failure('That does not look like a CSV file. Export your statement as CSV from your bank.', 422, ['file' => ['Choose a CSV file.']]);
        }
        return $text;
    }

    public function preview(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        if (!$acct = $this->account($b, $id)) {
            return $this->failure('Account not found.', 404);
        }
        $text = $this->readFile($request);
        if ($text instanceof JsonResponse) {
            return $text;
        }
        $info = StatementParser::inspect($text);
        if ($info['rows'] === 0) {
            return $this->failure('That file has no transactions in it.', 422, ['file' => ['The file is empty.']]);
        }
        if ($info['rows'] > self::MAX_LINES) {
            return $this->failure('That file has more than ' . number_format(self::MAX_LINES) . ' lines. Export a shorter date range.', 422, ['file' => ['Too many lines.']]);
        }
        return response()->json(['data' => $info + [
            'outflow_positive_suggested' => $acct->account_type === 'credit_card',
            'split_suggested' => $info['mapping']['amount'] === null && $info['mapping']['debit'] !== null,
        ]]);
    }

    public function store(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        if (!$acct = $this->account($b, $id)) {
            return $this->failure('Account not found.', 404);
        }
        $text = $this->readFile($request);
        if ($text instanceof JsonResponse) {
            return $text;
        }
        $map = json_decode((string) $request->input('mapping'), true);
        if (!is_array($map) || !isset($map['date'], $map['description'])
            || (!isset($map['amount']) && !(isset($map['debit']) && isset($map['credit'])))) {
            return $this->failure('Choose which columns hold the date, description and amount.', 422, ['mapping' => ['Match the columns first.']]);
        }
        $map = [
            'date' => (int) $map['date'], 'description' => (int) $map['description'],
            'amount' => isset($map['amount']) ? (int) $map['amount'] : null,
            'debit' => isset($map['debit']) ? (int) $map['debit'] : null,
            'credit' => isset($map['credit']) ? (int) $map['credit'] : null,
            'balance' => isset($map['balance']) ? (int) $map['balance'] : null,
        ];
        $parsed = StatementParser::parse($text, $map, $request->boolean('has_header'), $request->boolean('outflow_positive'));
        if (!$parsed['lines']) {
            return $this->failure('Nothing could be imported. ' . ($parsed['errors'][0] ?? ''), 422, ['mapping' => array_slice($parsed['errors'], 0, 5) ?: ['No lines found.']]);
        }

        $user = auth()->guard('api')->user() ?? $request->user();
        $result = DB::transaction(function () use ($b, $acct, $parsed, $request, $user) {
            $existing = BankTransaction::where('bank_account_id', $acct->id)
                ->whereIn('fingerprint', array_column($parsed['lines'], 'fingerprint'))->pluck('fingerprint')->flip();
            $new = array_values(array_filter($parsed['lines'], fn ($l) => !isset($existing[$l['fingerprint']])));

            $import = \DB::table('bank_imports')->insertGetId([
                'company_id' => $b->company_id, 'bank_account_id' => $acct->id,
                'file_name' => mb_substr((string) $request->file('file')->getClientOriginalName(), 0, 255),
                'lines_read' => count($parsed['lines']), 'lines_imported' => count($new),
                'lines_skipped' => count($parsed['lines']) - count($new), 'created_by' => optional($user)->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (array_chunk($new, 500) as $chunk) {
                DB::table('bank_transactions')->insertOrIgnore(array_map(fn ($l) => [
                    'company_id' => $b->company_id, 'bank_account_id' => $acct->id, 'import_id' => $import,
                    'txn_date' => $l['date'], 'description' => $l['description'], 'amount' => $l['amount'],
                    'balance_after' => $l['balance_after'], 'status' => 'review', 'fingerprint' => $l['fingerprint'],
                    'source' => 'csv', 'created_at' => now(), 'updated_at' => now(),
                ], $chunk));
            }
            $acct->last_imported_at = now();
            $acct->save();
            return ['import_id' => $import, 'imported' => count($new), 'skipped' => count($parsed['lines']) - count($new)];
        });

        $msg = $result['imported'] . ' transaction' . ($result['imported'] === 1 ? '' : 's') . ' imported'
            . ($result['skipped'] ? ', ' . $result['skipped'] . ' already there and skipped' : '') . '.';
        return response()->json(['data' => $result + ['unreadable' => count($parsed['errors']), 'problems' => array_slice($parsed['errors'], 0, 10)], 'message' => $msg], 201);
    }

    /** Undo an import: removes its lines that nobody has reviewed yet. Reviewed lines stay. */
    public function undo($id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $imp = DB::table('bank_imports')->where('company_id', $b->company_id)->where('id', $id)->first();
        if (!$imp) {
            return $this->failure('Import not found.', 404);
        }
        $removed = BankTransaction::where('company_id', $b->company_id)->where('import_id', $id)->where('status', 'review')->whereNull('reconciliation_id')->delete();
        return response()->json(['message' => $removed . ' unreviewed transaction' . ($removed === 1 ? '' : 's') . ' removed.']);
    }
}
