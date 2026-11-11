<?php

namespace App\Http\Controllers\Api\Banking;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\BankTransaction;
use App\Models\BusinessBankAccount;
use App\Models\User;
use App\Services\Banking\ReconcileMath;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Reconcile an account against its bank statement: tick the lines that are on the statement until
 * opening balance + ticked lines equals the statement's closing balance, then finish.
 * Only the latest reconciliation of an account can be undone.
 */
class ReconcileController extends Controller
{
    use SalesSupport;

    private const MAX_LINES = 1000;

    private function account($b, $id): ?BusinessBankAccount
    {
        return BusinessBankAccount::where('company_id', $b->company_id)->find($id);
    }

    private function latest($b, $accountId)
    {
        return DB::table('bank_reconciliations')->where('company_id', $b->company_id)->where('bank_account_id', $accountId)
            ->orderByDesc('statement_end')->orderByDesc('id')->first();
    }

    private function today(): string
    {
        return Carbon::now('Australia/Sydney')->toDateString();
    }

    /** Where to start: the opening balance carried over from the last reconciliation, and the past ones. */
    public function start($accountId): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        if (!$a = $this->account($b, $accountId)) {
            return $this->failure('Account not found.', 404);
        }
        $rows = DB::table('bank_reconciliations')->where('company_id', $b->company_id)->where('bank_account_id', $a->id)
            ->orderByDesc('statement_end')->orderByDesc('id')->limit(24)->get();
        $people = [];
        foreach (User::whereIn('id', $rows->pluck('finished_by')->filter()->unique()->all())->get() as $u) {
            $n = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
            $people[$u->id] = $n !== '' ? $n : ($u->name ?: $u->email);
        }
        $latestId = $rows->first()->id ?? null;
        $last = $rows->first();

        return response()->json(['data' => [
            'account' => ['id' => $a->id, 'name' => $a->name, 'type' => $a->account_type],
            'opening_balance' => $last ? $this->money($last->closing_balance) : null,
            'last_statement_end' => $last ? $last->statement_end : null,
            'history' => $rows->map(fn ($r) => [
                'id' => $r->id, 'statement_end' => $r->statement_end, 'opening_balance' => $this->money($r->opening_balance),
                'closing_balance' => $this->money($r->closing_balance), 'line_count' => (int) $r->line_count,
                'finished_by' => $r->finished_by ? ($people[$r->finished_by] ?? null) : null,
                'finished_at' => $r->created_at, 'can_undo' => $r->id === $latestId,
            ])->all(),
        ]]);
    }

    /** The lines that can still be ticked: not excluded, not reconciled, on or before the statement date. */
    public function lines(Request $request, $accountId): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        if (!$a = $this->account($b, $accountId)) {
            return $this->failure('Account not found.', 404);
        }
        $to = (string) $request->query('to', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || !strtotime($to)) {
            return $this->failure('Enter the statement end date.', 422, ['statement_end' => ['Enter the statement end date.']]);
        }
        $q = BankTransaction::where('company_id', $b->company_id)->where('bank_account_id', $a->id)
            ->where('status', '!=', 'excluded')->whereNull('reconciliation_id')->where('txn_date', '<=', $to);
        $total = (clone $q)->count();
        $rows = $q->orderBy('txn_date')->orderBy('id')->limit(self::MAX_LINES)->get();

        return response()->json(['data' => [
            'lines' => $rows->map(fn ($t) => ['id' => $t->id, 'date' => $t->txn_date->toDateString(), 'description' => $t->description, 'amount' => $this->money($t->amount)])->all(),
            'total' => $total,
            'truncated' => $total > self::MAX_LINES,
        ]]);
    }

    public function finish(Request $request, $accountId): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        if (!$a = $this->account($b, $accountId)) {
            return $this->failure('Account not found.', 404);
        }
        $last = $this->latest($b, $a->id);
        $v = Validator::make($request->all(), [
            'statement_end' => ['required', 'date_format:Y-m-d'],
            'closing_balance' => ['required', 'numeric', 'between:-9999999999,9999999999'],
            'opening_balance' => [$last ? 'nullable' : 'required', 'numeric', 'between:-9999999999,9999999999'],
            'ids' => ['present', 'array', 'max:' . self::MAX_LINES],
            'ids.*' => ['integer'],
        ], [
            'statement_end.required' => 'Enter the statement end date.', 'statement_end.date_format' => 'Enter the statement end date.',
            'closing_balance.required' => 'Enter the closing balance from your statement.', 'closing_balance.numeric' => 'Enter the closing balance as a number.',
            'opening_balance.required' => 'Enter the opening balance from your statement.', 'opening_balance.numeric' => 'Enter the opening balance as a number.',
        ]);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $d = $v->validated();
        if ($d['statement_end'] > $this->today()) {
            return $this->failure('Please fix the highlighted fields.', 422, ['statement_end' => ['The statement end date cannot be in the future.']]);
        }
        if ($last && $d['statement_end'] <= $last->statement_end) {
            return $this->failure('Please fix the highlighted fields.', 422, ['statement_end' => ['Choose a date after your last reconciliation (' . $last->statement_end . ').']]);
        }
        $opening = $last ? $last->closing_balance : $d['opening_balance'];
        $ids = array_values(array_unique(array_map('intval', $d['ids'])));

        $lines = BankTransaction::where('company_id', $b->company_id)->where('bank_account_id', $a->id)->whereIn('id', $ids)
            ->where('status', '!=', 'excluded')->whereNull('reconciliation_id')->where('txn_date', '<=', $d['statement_end'])->get();
        if ($lines->count() !== count($ids)) {
            return $this->failure('Some of the ticked transactions have changed. Please start again.', 409);
        }
        $diff = ReconcileMath::difference($opening, $d['closing_balance'], $lines->pluck('amount')->all());
        if ($diff !== 0) {
            return $this->failure('The difference is ' . ReconcileMath::dollars($diff) . ', so this cannot be finished yet.', 422, ['difference' => [ReconcileMath::dollars($diff)]]);
        }

        $user = auth()->guard('api')->user() ?? $request->user();
        $id = DB::transaction(function () use ($b, $a, $d, $opening, $ids, $user) {
            $rid = DB::table('bank_reconciliations')->insertGetId([
                'company_id' => $b->company_id, 'bank_account_id' => $a->id, 'statement_end' => $d['statement_end'],
                'opening_balance' => $opening, 'closing_balance' => $d['closing_balance'], 'line_count' => count($ids),
                'finished_by' => optional($user)->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $n = $ids ? BankTransaction::where('company_id', $b->company_id)->whereIn('id', $ids)->whereNull('reconciliation_id')
                ->update(['reconciliation_id' => $rid, 'updated_at' => now()]) : 0;
            if ($n !== count($ids)) {
                throw new \RuntimeException('Reconciliation changed while saving.');
            }
            return $rid;
        });

        return response()->json(['data' => ['id' => $id, 'line_count' => count($ids)],
            'message' => 'Reconciled. ' . count($ids) . ' transaction' . (count($ids) === 1 ? '' : 's') . ' agreed with your statement.'], 201);
    }

    /** Undo the latest reconciliation of an account. The ticked lines become available again. */
    public function undo($id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $r = DB::table('bank_reconciliations')->where('company_id', $b->company_id)->where('id', $id)->first();
        if (!$r) {
            return $this->failure('Reconciliation not found.', 404);
        }
        $latest = $this->latest($b, $r->bank_account_id);
        if (!$latest || $latest->id !== $r->id) {
            return $this->failure('Only the latest reconciliation of an account can be undone. Undo the newer one first.', 422);
        }
        DB::transaction(function () use ($b, $r) {
            BankTransaction::where('company_id', $b->company_id)->where('reconciliation_id', $r->id)->update(['reconciliation_id' => null, 'updated_at' => now()]);
            DB::table('bank_reconciliations')->where('id', $r->id)->delete();
        });
        return response()->json(['message' => 'Reconciliation undone.']);
    }
}
