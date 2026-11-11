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

    private function listing(Request $request, $accountId): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $base = BankTransaction::where('company_id', $b->company_id);
        if ($accountId !== null && $accountId !== '') {
            $acct = BusinessBankAccount::where('company_id', $b->company_id)->find($accountId);
            if (!$acct) {
                return $this->failure('Account not found.', 404);
            }
            $base->where('bank_account_id', $acct->id);
        }
        $status = in_array($request->query('status'), ['review', 'done', 'excluded'], true) ? $request->query('status') : 'review';
        $counts = (clone $base)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $q = (clone $base)->where('status', $status);
        if ($s = trim((string) $request->query('search', ''))) {
            $q->where('description', 'like', '%' . $s . '%');
        }
        $page = $q->orderByDesc('txn_date')->orderByDesc('id')->paginate(min(max((int) $request->query('per_page', 25), 1), 100));
        $names = BusinessBankAccount::where('company_id', $b->company_id)->pluck('name', 'id');

        return response()->json([
            'data' => $page->getCollection()->map(fn ($t) => $this->row($t) + ['account_id' => $t->bank_account_id, 'account_name' => $names[$t->bank_account_id] ?? ''])->values(),
            'counts' => ['review' => (int) ($counts['review'] ?? 0), 'done' => (int) ($counts['done'] ?? 0), 'excluded' => (int) ($counts['excluded'] ?? 0)],
            'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
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
        $t->status = $to;
        $t->save();
        return response()->json(['data' => $this->row($t), 'message' => $to === 'excluded' ? 'Excluded.' : 'Back in review.']);
    }
}
