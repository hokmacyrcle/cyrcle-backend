<?php

namespace App\Http\Controllers\Api\Banking;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\BankTransaction;
use App\Models\BusinessBankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** The bank, savings and credit card accounts of the business the user is working in. */
class BankAccountController extends Controller
{
    use SalesSupport;

    private function row(BusinessBankAccount $a, array $counts = []): array
    {
        $num = (string) $a->account_number;
        return [
            'id' => $a->id,
            'name' => $a->name,
            'type' => $a->account_type,
            'type_label' => BusinessBankAccount::TYPES[$a->account_type] ?? $a->account_type,
            'bsb' => $a->bsb,
            'account_number' => $a->account_number,
            'last4' => $num !== '' ? substr($num, -4) : null,
            'active' => (bool) $a->active,
            'last_imported_at' => optional($a->last_imported_at)->toDateString(),
            'to_review' => $counts[$a->id] ?? 0,
            'feed_connected' => (bool) $a->feed_provider,
            'feed_link_id' => $a->feed_link_id,
            'bank_balance' => $a->bank_balance,
            'last_synced_at' => optional($a->last_synced_at)->toIso8601String(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $archived = $request->query('status') === 'archived';
        $accounts = BusinessBankAccount::where('company_id', $b->company_id)->where('active', !$archived)->orderBy('name')->get();
        $counts = BankTransaction::where('company_id', $b->company_id)->where('status', 'review')
            ->selectRaw('bank_account_id, COUNT(*) as n')->groupBy('bank_account_id')->pluck('n', 'bank_account_id')->all();
        return response()->json(['data' => $accounts->map(fn ($a) => $this->row($a, $counts))->values()]);
    }

    private function validated(Request $request, int $companyId, ?int $ignoreId)
    {
        $type = $request->input('type');
        $v = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100', Rule::unique('business_bank_accounts', 'name')->where('company_id', $companyId)->where('active', true)->ignore($ignoreId)],
            'type' => ['required', Rule::in(array_keys(BusinessBankAccount::TYPES))],
            'bsb' => ['nullable', 'regex:/^\d{3}-?\d{3}$/'],
            'account_number' => ['nullable', 'regex:' . ($type === 'credit_card' ? '/^\d{4}$/' : '/^\d{5,10}$/')],
        ], [
            'name.required' => 'Enter a name for this account.',
            'name.unique' => 'You already have an account with this name.',
            'type.required' => 'Choose the type of account.',
            'type.in' => 'Choose the type of account.',
            'bsb.regex' => 'A BSB has 6 digits, for example 062-000.',
            'account_number.regex' => $type === 'credit_card' ? 'Enter the last 4 digits of the card.' : 'An account number has 5 to 10 digits.',
        ]);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $bsb = $request->bsb ? preg_replace('/^(\d{3})-?(\d{3})$/', '$1-$2', $request->bsb) : null;
        return [
            'name' => trim($request->name),
            'account_type' => $type,
            'bsb' => $type === 'credit_card' ? null : $bsb,
            'account_number' => $request->account_number ?: null,
        ];
    }

    public function store(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $data = $this->validated($request, (int) $b->company_id, null);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $a = BusinessBankAccount::create($data + ['company_id' => $b->company_id, 'active' => true]);
        return response()->json(['data' => $this->row($a), 'message' => 'Account added.'], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $a = BusinessBankAccount::where('company_id', $b->company_id)->find($id);
        if (!$a) {
            return $this->failure('Account not found.', 404);
        }
        $data = $this->validated($request, (int) $b->company_id, (int) $a->id);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $a->update($data);
        return response()->json(['data' => $this->row($a), 'message' => 'Saved.']);
    }

    /** Archive or restore. Transactions stay; nothing is deleted. */
    public function archive(Request $request, $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $a = BusinessBankAccount::where('company_id', $b->company_id)->find($id);
        if (!$a) {
            return $this->failure('Account not found.', 404);
        }
        $a->active = $request->boolean('archived') ? false : true;
        $a->save();
        return response()->json(['data' => $this->row($a), 'message' => $a->active ? 'Account restored.' : 'Account archived.']);
    }
}
