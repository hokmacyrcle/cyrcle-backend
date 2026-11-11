<?php

namespace App\Http\Controllers\Api\Banking;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\BankTransaction;
use App\Models\BusinessBankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The transactions of one bank account, grouped by review status. */
class BankTransactionController extends Controller
{
    use SalesSupport;

    private function row(BankTransaction $t): array
    {
        return [
            'id' => $t->id,
            'date' => $t->txn_date->toDateString(),
            'description' => $t->description,
            'amount' => $this->money($t->amount),
            'status' => $t->status,
        ];
    }

    /** One account's transactions. */
    public function index(Request $request, $id): JsonResponse
    {
        return $this->listing($request, $id);
    }

    /** Transactions of every account, or of one when ?account= is given. */
    public function all(Request $request): JsonResponse
    {
        return $this->listing($request, $request->query('account'));
    }

    /** The lines of this business, narrowed by account, status, search text, dates and money in/out. */
    private function filtered(Request $request, $companyId, $accountId)
    {
        $q = BankTransaction::where('company_id', $companyId);
        if ($accountId !== null && $accountId !== '') {
            $q->where('bank_account_id', (int) $accountId);
        }
        return $q;
    }

    private function narrow($q, Request $request, string $status)
    {
        $q->where('status', $status);
        if ($s = trim((string) $request->query('search', ''))) {
            $q->where('description', 'like', '%' . $s . '%');
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            $d = (string) $request->query($key, '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $q->where('txn_date', $op, $d);
            }
        }
        if ($request->query('type') === 'in') {
            $q->where('amount', '>', 0);
        } elseif ($request->query('type') === 'out') {
            $q->where('amount', '<', 0);
        }
        return $q;
    }

    private function listing(Request $request, $accountId): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        if ($accountId !== null && $accountId !== ''
            && !BusinessBankAccount::where('company_id', $b->company_id)->find($accountId)) {
            return $this->failure('Account not found.', 404);
        }
        $base = $this->filtered($request, $b->company_id, $accountId);
        $status = in_array($request->query('status'), ['review', 'done', 'excluded'], true) ? $request->query('status') : 'review';
        $counts = (clone $base)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $q = $this->narrow(clone $base, $request, $status);
        $page = $q->orderByDesc('txn_date')->orderByDesc('id')->paginate(min(max((int) $request->query('per_page', 25), 1), 100));
        $names = BusinessBankAccount::where('company_id', $b->company_id)->pluck('name', 'id');

        return response()->json([
            'data' => $page->getCollection()->map(fn ($t) => $this->row($t) + ['account_id' => $t->bank_account_id, 'account_name' => $names[$t->bank_account_id] ?? ''])->values(),
            'counts' => ['review' => (int) ($counts['review'] ?? 0), 'done' => (int) ($counts['done'] ?? 0), 'excluded' => (int) ($counts['excluded'] ?? 0)],
            'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }

    /** Download what the table is showing (same account, tab and filters) as a CSV file that opens in Excel. */
    public function export(Request $request)
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $accountId = $request->query('account');
        if ($accountId !== null && $accountId !== ''
            && !BusinessBankAccount::where('company_id', $b->company_id)->find($accountId)) {
            return $this->failure('Account not found.', 404);
        }
        $status = in_array($request->query('status'), ['review', 'done', 'excluded'], true) ? $request->query('status') : 'review';
        $q = $this->narrow($this->filtered($request, $b->company_id, $accountId), $request, $status)->orderByDesc('txn_date')->orderByDesc('id');
        $names = BusinessBankAccount::where('company_id', $b->company_id)->pluck('name', 'id');
        $labels = ['review' => 'For review', 'done' => 'Done', 'excluded' => 'Excluded'];
        // A cell that starts with = + - or @ can run as a formula when opened in Excel, so it is marked as plain text.
        $safe = fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v;

        $file = 'cyrcle-bank-transactions-' . \Carbon\Carbon::now('Australia/Sydney')->toDateString() . '.csv';
        return response()->streamDownload(function () use ($q, $names, $labels, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Description', 'Account', 'Money out', 'Money in', 'Amount', 'Balance', 'Status']);
            $q->chunk(1000, function ($rows) use ($out, $names, $labels, $safe) {
                foreach ($rows as $t) {
                    $amt = (float) $t->amount;
                    fputcsv($out, [
                        $t->txn_date->format('d/m/Y'), $safe($t->description), $safe($names[$t->bank_account_id] ?? ''),
                        $amt < 0 ? number_format(-$amt, 2, '.', '') : '', $amt > 0 ? number_format($amt, 2, '.', '') : '',
                        number_format($amt, 2, '.', ''), $t->balance_after === null ? '' : number_format((float) $t->balance_after, 2, '.', ''),
                        $labels[$t->status] ?? $t->status,
                    ]);
                }
            });
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Numbers for the Banking overview: what needs review and what moved in the last 30 days. */
    public function summary(): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $from = \Carbon\Carbon::now('Australia/Sydney')->subDays(30)->toDateString();
        $base = BankTransaction::where('company_id', $b->company_id);
        $last30 = (clone $base)->where('status', '!=', 'excluded')->where('txn_date', '>=', $from);
        $names = BusinessBankAccount::where('company_id', $b->company_id)->pluck('name', 'id');
        $recent = (clone $base)->where('status', 'review')->orderByDesc('txn_date')->orderByDesc('id')->limit(5)->get()
            ->map(fn ($t) => $this->row($t) + ['account_id' => $t->bank_account_id, 'account_name' => $names[$t->bank_account_id] ?? ''])->values();

        return response()->json(['data' => [
            'accounts' => BusinessBankAccount::where('company_id', $b->company_id)->where('active', true)->count(),
            'to_review' => (clone $base)->where('status', 'review')->count(),
            'money_in_30' => $this->money((clone $last30)->where('amount', '>', 0)->sum('amount')),
            'money_out_30' => $this->money(abs((clone $last30)->where('amount', '<', 0)->sum('amount'))),
            'recent' => $recent,
        ]]);
    }

    /** Exclude a line (a personal or duplicate entry) or put it back for review. */
    public function setStatus(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $t = BankTransaction::where('company_id', $b->company_id)->find($id);
        if (!$t) {
            return $this->failure('Transaction not found.', 404);
        }
        $to = $request->input('status');
        if (!in_array($to, ['excluded', 'review'], true)) {
            return $this->failure('Choose excluded or review.', 422);
        }
        if ($t->status === 'done') {
            return $this->failure('This transaction is already done. Undo the match first.');
        }
        if ($t->reconciliation_id) {
            return $this->failure('This transaction has been reconciled. Undo that reconciliation first.');
        }
        $t->status = $to;
        $t->save();
        return response()->json(['data' => $this->row($t), 'message' => $to === 'excluded' ? 'Excluded.' : 'Back in review.']);
    }

    /** Exclude or put back several lines at once. Lines that are already done or reconciled are left alone. */
    public function bulkStatus(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $to = $request->input('status');
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        if (!in_array($to, ['excluded', 'review'], true) || !$ids) {
            return $this->failure('Choose the lines and whether to exclude them or put them back.', 422);
        }
        $changed = BankTransaction::where('company_id', $b->company_id)->whereIn('id', array_slice($ids, 0, 500))
            ->whereNull('reconciliation_id')->where('status', '!=', 'done')->where('status', '!=', $to)->update(['status' => $to, 'updated_at' => now()]);
        return response()->json(['data' => ['changed' => $changed],
            'message' => $changed . ' transaction' . ($changed === 1 ? '' : 's') . ($to === 'excluded' ? ' excluded.' : ' put back.')]);
    }
}
