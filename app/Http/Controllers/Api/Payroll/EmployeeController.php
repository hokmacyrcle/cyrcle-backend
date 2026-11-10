<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\BankAccount;
use App\Models\Employee;
use App\Models\EmploymentInformation;
use App\Models\SuperannuationFund;
use App\Models\TaxDetail;
use App\Rules\ValidTfn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Employees of the business being worked in: one form covering personal, job, pay, tax, super and bank details.
 * Everything is limited to the selected business. The tax file number is stored encrypted and never sent back in full.
 */
class EmployeeController extends Controller
{
    use SalesSupport;

    /** Legislated super guarantee rate (from 1 July 2025). */
    private const SUPER_RATE = 12.00;

    private const STATES = ['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'ACT', 'NT'];
    private const BASES = ['Full-time', 'Part-time', 'Casual', 'Fixed-term'];
    private const FREQUENCIES = ['Weekly', 'Fortnightly', 'Monthly'];
    /** Our wording on the left; the value the existing employees table accepts on the right. */
    private const TYPE_MAP = ['Full-time' => 'Full Time', 'Part-time' => 'Part Time', 'Casual' => 'Casual', 'Fixed-term' => 'Contract'];

    public function index(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $cid = (int) $b->company_id;
        $base = Employee::where('emp_company_id', $cid);
        $counts = [
            'active' => (clone $base)->where('emp_status', 'Active')->count(),
            'archived' => (clone $base)->where('emp_status', '!=', 'Active')->count(),
        ];

        $q = (clone $base);
        $request->query('status') === 'archived' ? $q->where('emp_status', '!=', 'Active') : $q->where('emp_status', 'Active');
        if ($s = trim((string) $request->query('search', ''))) {
            $like = '%' . addcslashes($s, '%_\\') . '%';
            $q->where(function ($w) use ($like) {
                $w->where('emp_first_name', 'like', $like)->orWhere('emp_last_name', 'like', $like)
                  ->orWhere('emp_email_address', 'like', $like)->orWhere('emp_job_title', 'like', $like);
            });
        }
        $page = $q->orderBy('emp_first_name')->orderBy('emp_last_name')->paginate(15);
        $ids = $page->pluck('emp_id');
        $jobs = EmploymentInformation::whereIn('employee_id', $ids)->get()->keyBy('employee_id');

        return response()->json([
            'data' => $page->getCollection()->map(fn (Employee $e) => $this->row($e, $jobs->get($e->emp_id)))->values(),
            'counts' => $counts,
            'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $e = Employee::where('emp_company_id', $b->company_id)->where('emp_id', $id)->first();
        return $e ? response()->json(['data' => $this->full($e)]) : $this->failure('Employee not found.', 404);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $v = $this->validated($request, null);
        if ($v instanceof JsonResponse) {
            return $v;
        }
        $e = DB::transaction(function () use ($b, $v) {
            $e = new Employee();
            $e->forceFill(['emp_company_id' => $b->company_id, 'emp_status' => 'Active'] + $this->employeeColumns($v))->save();
            $this->saveRelated($e, $v, true);
            return $e;
        });
        return response()->json(['data' => $this->full($e->fresh())], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $e = Employee::where('emp_company_id', $b->company_id)->where('emp_id', $id)->first();
        if (!$e) {
            return $this->failure('Employee not found.', 404);
        }
        $v = $this->validated($request, $e);
        if ($v instanceof JsonResponse) {
            return $v;
        }
        DB::transaction(function () use ($e, $v) {
            $e->forceFill($this->employeeColumns($v))->save();
            $this->saveRelated($e, $v, false);
        });
        return response()->json(['data' => $this->full($e->fresh())]);
    }

    /** Archive (inactive) or bring back an employee. Records are never deleted: pay history must stay. */
    public function setArchived(Request $request, int $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $e = Employee::where('emp_company_id', $b->company_id)->where('emp_id', $id)->first();
        if (!$e) {
            return $this->failure('Employee not found.', 404);
        }
        $archive = $request->boolean('archived');
        $e->forceFill(['emp_status' => $archive ? 'Inactive' : 'Active'])->save();
        return response()->json(['data' => $this->full($e->fresh())]);
    }

    // ---------------------------------------------------------------- validation

    private function validated(Request $request, ?Employee $existing)
    {
        $in = $request->all();
        foreach (['tfn', 'bank_bsb', 'bank_account_number', 'super_usi'] as $k) {
            if (isset($in[$k]) && is_string($in[$k])) {
                $in[$k] = preg_replace('/\s+/', '', $in[$k]);
            }
        }
        $hasTaxRow = $existing ? TaxDetail::where('employee_id', $existing->emp_id)->whereNotNull('tax_file_number')->exists() : false;

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before:-14 years', 'after:1900-01-01'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'regex:/^[0-9 +()\-]{8,20}$/'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'suburb' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', Rule::in(self::STATES)],
            'postcode' => ['nullable', 'digits:4'],

            'job_title' => ['nullable', 'string', 'max:100'],
            'employment_basis' => ['required', Rule::in(self::BASES)],
            'start_date' => ['required', 'date', 'after:1950-01-01'],
            'pay_frequency' => ['required', Rule::in(self::FREQUENCIES)],
            'pay_basis' => ['required', 'in:hourly,salary'],
            'hourly_rate' => ['required_if:pay_basis,hourly', 'nullable', 'numeric', 'gt:0', 'max:9999.99', $this->twoDecimals('Hourly rate')],
            'annual_salary' => ['required_if:pay_basis,salary', 'nullable', 'numeric', 'gt:0', 'max:9999999.99', $this->twoDecimals('Salary')],
            'hours_per_week' => ['nullable', 'numeric', 'gt:0', 'max:168', $this->twoDecimals('Hours')],

            'tfn' => ['nullable', 'regex:/^\d{9}$/', new ValidTfn()],
            'residency' => ['required', 'in:Australian resident,Foreign resident'],
            'claim_tax_free_threshold' => ['nullable', 'boolean'],
            'has_study_training_loans' => ['nullable', 'boolean'],
            'working_holiday_maker' => ['nullable', 'boolean'],

            'super_fund_name' => ['nullable', 'string', 'max:255'],
            'super_usi' => ['nullable', 'required_with:super_fund_name', 'regex:/^[A-Za-z0-9]{9,14}$/'],
            'super_member_number' => ['nullable', 'required_with:super_fund_name', 'string', 'max:50'],

            'bank_account_name' => ['nullable', 'required_with:bank_bsb,bank_account_number', 'string', 'max:100'],
            'bank_bsb' => ['nullable', 'required_with:bank_account_name,bank_account_number', 'regex:/^\d{3}-?\d{3}$/'],
            'bank_account_number' => ['nullable', 'required_with:bank_account_name,bank_bsb', 'regex:/^\d{6,9}$/'],
        ];
        $messages = [
            'first_name.required' => 'Enter a first name.',
            'last_name.required' => 'Enter a last name.',
            'date_of_birth.before' => 'Employees must be at least 14 years old.',
            'mobile.regex' => 'Enter a valid phone number.',
            'postcode.digits' => 'Postcode must be 4 digits.',
            'state.in' => 'Choose a state or territory.',
            'start_date.required' => 'Enter the start date.',
            'pay_frequency.required' => 'Choose how often this employee is paid.',
            'pay_basis.required' => 'Choose hourly or salary.',
            'hourly_rate.required_if' => 'Enter the hourly rate.',
            'annual_salary.required_if' => 'Enter the yearly salary.',
            'tfn.regex' => 'A tax file number has 9 digits.',
            'residency.required' => 'Choose the tax residency.',
            'super_usi.required_with' => 'Enter the fund USI, or leave the fund name empty.',
            'super_usi.regex' => 'A USI is 9 to 14 letters and numbers.',
            'super_member_number.required_with' => 'Enter the member number, or leave the fund name empty.',
            'bank_bsb.regex' => 'A BSB is 6 digits, like 062-000.',
            'bank_bsb.required_with' => 'Enter all three bank details, or leave them empty.',
            'bank_account_number.regex' => 'An account number is 6 to 9 digits.',
            'bank_account_number.required_with' => 'Enter all three bank details, or leave them empty.',
            'bank_account_name.required_with' => 'Enter all three bank details, or leave them empty.',
        ];
        $validator = Validator::make($in, $rules, $messages);
        if ($validator->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $validator->errors()->toArray());
        }
        $v = $validator->validated();
        $v['_keep_tfn'] = empty($v['tfn']) && $hasTaxRow;
        return $v;
    }

    // ---------------------------------------------------------------- saving

    private function employeeColumns(array $v): array
    {
        return [
            'emp_first_name' => trim($v['first_name']),
            'emp_middle_name' => $v['middle_name'] ?? null,
            'emp_last_name' => trim($v['last_name']),
            'emp_date_of_birth' => $v['date_of_birth'] ?? null,
            'emp_email_address' => $v['email'] ?? null,
            'emp_mobile_number' => $v['mobile'] ?? null,
            'emp_address_line_1' => $v['address_line'] ?? null,
            'emp_city' => $v['suburb'] ?? null,
            'emp_state' => $v['state'] ?? null,
            'emp_post_code' => $v['postcode'] ?? null,
            'emp_country' => 'Australia',
            'emp_job_title' => $v['job_title'] ?? null,
            'emp_start_date' => $v['start_date'],
            'emp_employment_type' => self::TYPE_MAP[$v['employment_basis']],
        ];
    }

    private function saveRelated(Employee $e, array $v, bool $creating): void
    {
        $eid = $e->emp_id;

        $job = EmploymentInformation::where('employee_id', $eid)->first() ?: new EmploymentInformation();
        $job->forceFill([
            'employee_id' => $eid,
            'employment_type' => 'Employee',
            'income_type' => $job->income_type ?: 'Salary and wages',
            'employment_basis' => $v['employment_basis'],
            'start_date' => $v['start_date'],
            'pay_frequency' => $v['pay_frequency'],
            'pay_basis' => $v['pay_basis'],
            'hourly_rate' => $v['pay_basis'] === 'hourly' ? $v['hourly_rate'] : null,
            'annual_salary' => $v['pay_basis'] === 'salary' ? $v['annual_salary'] : null,
            'hours_per_week' => $v['hours_per_week'] ?? null,
            'ordinary_earnings_rate' => $job->ordinary_earnings_rate ?: 'Ordinary Hours',
            'is_active' => true,
        ])->save();

        $tax = TaxDetail::where('employee_id', $eid)->first() ?: new TaxDetail();
        $foreign = $v['residency'] === 'Foreign resident';
        $hasTfn = !empty($v['tfn']) || $v['_keep_tfn'];
        $whm = !empty($v['working_holiday_maker']);
        $attrs = [
            'employee_id' => $eid,
            'residency_status' => $v['residency'],
            // A foreign resident cannot claim the tax-free threshold.
            'claim_tax_free_threshold' => $foreign ? false : (bool) ($v['claim_tax_free_threshold'] ?? false),
            'has_study_training_loans' => (bool) ($v['has_study_training_loans'] ?? false),
            'tax_scale_type' => !$hasTfn ? 'No TFN' : ($whm ? 'Working holiday maker' : 'Regular'),
            'is_active' => true,
        ];
        if (!empty($v['tfn'])) {
            $attrs['tax_file_number'] = Crypt::encryptString($v['tfn']);
        }
        $tax->forceFill($attrs)->save();

        if (!empty($v['bank_bsb'])) {
            $bsb = substr($v['bank_bsb'], 0, 3) . '-' . substr(str_replace('-', '', $v['bank_bsb']), 3, 3);
            $bank = BankAccount::where('employee_id', $eid)->where('is_primary_account', 1)->first() ?: new BankAccount();
            $bank->forceFill([
                'employee_id' => $eid,
                'account_name' => $v['bank_account_name'],
                'bsb_number' => $bsb,
                'account_number' => $v['bank_account_number'],
                'statement_text' => $bank->statement_text ?: 'Salary',
                'is_primary_account' => true,
                'status' => 'active',
            ])->save();
        } else {
            BankAccount::where('employee_id', $eid)->where('is_primary_account', 1)->delete();
        }

        if (!empty($v['super_fund_name'])) {
            $fund = SuperannuationFund::where('employee_id', $eid)->first() ?: new SuperannuationFund();
            $fund->forceFill([
                'employee_id' => $eid,
                'fund_name' => $v['super_fund_name'],
                'usi' => $v['super_usi'],
                'member_number' => $v['super_member_number'],
                'contribution_type' => 'statutory_rate',
                'is_statutory_rate' => true,
                'contribution_value' => self::SUPER_RATE,
                'status' => 'active',
            ])->save();
        } else {
            SuperannuationFund::where('employee_id', $eid)->delete();
        }
    }

    // ---------------------------------------------------------------- output

    private function name(Employee $e): string
    {
        return trim($e->emp_first_name . ' ' . $e->emp_last_name);
    }

    private function paySummary(?EmploymentInformation $j): ?string
    {
        if (!$j) {
            return null;
        }
        if ($j->pay_basis === 'salary' && $j->annual_salary !== null) {
            return '$' . number_format((float) $j->annual_salary, 2) . ' a year';
        }
        if ($j->pay_basis === 'hourly' && $j->hourly_rate !== null) {
            return '$' . number_format((float) $j->hourly_rate, 2) . ' an hour';
        }
        return null;
    }

    private function row(Employee $e, ?EmploymentInformation $j): array
    {
        return [
            'id' => $e->emp_id,
            'name' => $this->name($e),
            'job_title' => $e->emp_job_title,
            'email' => $e->emp_email_address,
            'mobile' => $e->emp_mobile_number,
            'employment_basis' => $j?->employment_basis,
            'pay_frequency' => $j?->pay_frequency,
            'pay_summary' => $this->paySummary($j),
            'start_date' => $e->emp_start_date?->toDateString(),
            'status' => $e->emp_status === 'Active' ? 'active' : 'archived',
        ];
    }

    /** Last three digits only: enough to recognise it, not enough to misuse it. */
    private function tfnLast3(?TaxDetail $t): ?string
    {
        $raw = $t?->getRawOriginal('tax_file_number');
        if (!$raw) {
            return null;
        }
        try {
            $plain = Crypt::decryptString($raw);
        } catch (\Throwable $ex) {
            $plain = preg_match('/^\d{9}$/', $raw) ? $raw : null; // older plain-text entries
        }
        return $plain ? substr($plain, -3) : null;
    }

    private function full(Employee $e): array
    {
        $j = EmploymentInformation::where('employee_id', $e->emp_id)->first();
        $t = TaxDetail::where('employee_id', $e->emp_id)->first();
        $bank = BankAccount::where('employee_id', $e->emp_id)->where('is_primary_account', 1)->first();
        $fund = SuperannuationFund::where('employee_id', $e->emp_id)->first();

        return $this->row($e, $j) + [
            'first_name' => $e->emp_first_name,
            'middle_name' => $e->emp_middle_name,
            'last_name' => $e->emp_last_name,
            'date_of_birth' => $e->emp_date_of_birth?->toDateString(),
            'address_line' => $e->emp_address_line_1,
            'suburb' => $e->emp_city,
            'state' => $e->emp_state,
            'postcode' => $e->emp_post_code,
            'pay_basis' => $j?->pay_basis,
            'hourly_rate' => $j?->hourly_rate !== null ? (float) $j->hourly_rate : null,
            'annual_salary' => $j?->annual_salary !== null ? (float) $j->annual_salary : null,
            'hours_per_week' => $j?->hours_per_week !== null ? (float) $j->hours_per_week : null,
            'tax' => [
                'tfn_last3' => $this->tfnLast3($t),
                'residency' => $t?->residency_status ?: 'Australian resident',
                'claim_tax_free_threshold' => $t ? (bool) $t->claim_tax_free_threshold : true,
                'has_study_training_loans' => (bool) ($t?->has_study_training_loans),
                'working_holiday_maker' => $t?->tax_scale_type === 'Working holiday maker',
                'scale' => $t?->tax_scale_type,
            ],
            'super' => $fund ? [
                'fund_name' => $fund->fund_name, 'usi' => $fund->usi, 'member_number' => $fund->member_number,
                'rate' => (float) $fund->contribution_value,
            ] : null,
            'bank' => $bank ? [
                'account_name' => $bank->account_name, 'bsb' => $bank->bsb_number, 'account_number' => $bank->account_number,
            ] : null,
            'super_rate' => self::SUPER_RATE,
        ];
    }
}
