<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\Employee;
use App\Models\EmploymentInformation;
use App\Models\PayRun;
use App\Models\PayRunLine;
use App\Models\PayRunLineItem;
use App\Models\PayrollSetting;
use App\Models\SuperannuationFund;
use App\Models\TaxDetail;
use App\Services\Payroll\PayCalculator as Calc;
use App\Services\Payroll\TimesheetHours;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Pay runs for the business being worked in. A run is built from the current employees, calculated in whole cents,
 * reviewed line by line, then finalised. A finalised run can no longer be changed.
 */
class PayRunController extends Controller
{
    use SalesSupport;

    private const FREQUENCIES = [Calc::WEEKLY, Calc::FORTNIGHTLY, Calc::MONTHLY];

    public function index(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $q = PayRun::where('company_id', $b->company_id);
        if (in_array($request->query('status'), ['draft', 'finalised'], true)) {
            $q->where('status', $request->query('status'));
        }
        $page = $q->orderByDesc('pay_date')->orderByDesc('id')->paginate(15);
        $counts = PayRunLine::whereIn('pay_run_id', $page->pluck('id'))->selectRaw('pay_run_id, COUNT(*) c')->groupBy('pay_run_id')->pluck('c', 'pay_run_id');

        return response()->json([
            'data' => $page->getCollection()->map(fn (PayRun $r) => $this->row($r, (int) ($counts[$r->id] ?? 0)))->values(),
            'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $run = PayRun::where('company_id', $b->company_id)->where('id', $id)->first();
        return $run ? response()->json(['data' => $this->full($run, $b)]) : $this->failure('Pay run not found.', 404);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $v = Validator::make($request->all(), [
            'frequency' => ['required', Rule::in(self::FREQUENCIES)],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'pay_date' => ['required', 'date', 'after_or_equal:period_start'],
        ], [
            'frequency.required' => 'Choose how often this group is paid.',
            'period_start.required' => 'Enter the first day of the pay period.',
            'period_end.required' => 'Enter the last day of the pay period.',
            'period_end.after_or_equal' => 'The last day cannot be before the first day.',
            'pay_date.required' => 'Enter the day employees are paid.',
            'pay_date.after_or_equal' => 'The pay date cannot be before the period starts.',
        ]);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $d = $v->validated();
        $start = Carbon::parse($d['period_start'])->startOfDay();
        $end = Carbon::parse($d['period_end'])->startOfDay();
        if ($start->diffInDays($end) > 34) {
            return $this->failure('A pay period cannot be longer than 5 weeks.', 422, ['period_end' => 'A pay period cannot be longer than 5 weeks.']);
        }

        $clash = PayRun::where('company_id', $b->company_id)->where('frequency', $d['frequency'])
            ->whereDate('period_start', '<=', $end->toDateString())->whereDate('period_end', '>=', $start->toDateString())->first();
        if ($clash) {
            return $this->failure('There is already a ' . strtolower($d['frequency']) . ' pay run (' . $clash->reference . ') that overlaps these dates.', 422, ['period_start' => 'Overlaps ' . $clash->reference . '.']);
        }

        $people = Employee::where('emp_company_id', $b->company_id)->where('emp_status', 'Active')->get();
        $jobs = EmploymentInformation::whereIn('employee_id', $people->pluck('emp_id'))->get()->keyBy('employee_id');
        $included = $people->filter(function (Employee $e) use ($jobs, $d, $end) {
            $j = $jobs->get($e->emp_id);
            return $j && $j->pay_frequency === $d['frequency'] && $j->pay_basis
                && (!$j->start_date || $j->start_date->startOfDay()->lte($end));
        })->values();
        if ($included->isEmpty()) {
            return $this->failure('No current employees are paid ' . strtolower($d['frequency']) . ' yet. Add an employee on that schedule first.', 422);
        }

        $run = DB::transaction(function () use ($b, $d, $included, $jobs) {
            $run = PayRun::create([
                'company_id' => $b->company_id,
                'reference' => $this->nextNumber('pay_runs', 'reference', (int) $b->company_id, 'PR'),
                'frequency' => $d['frequency'],
                'period_start' => $d['period_start'], 'period_end' => $d['period_end'], 'pay_date' => $d['pay_date'],
                'status' => 'draft', 'super_rate' => PayrollSetting::forCompany((int) $b->company_id)['super_rate'], 'created_by' => auth()->guard('api')->id(),
            ]);
            foreach ($included as $e) {
                $j = $jobs->get($e->emp_id);
                $fund = SuperannuationFund::where('employee_id', $e->emp_id)->first();
                $line = new PayRunLine([
                    'pay_run_id' => $run->id, 'employee_id' => $e->emp_id,
                    'employee_name' => trim($e->emp_first_name . ' ' . $e->emp_last_name),
                    'pay_basis' => $j->pay_basis, 'hourly_rate' => $j->hourly_rate, 'annual_salary' => $j->annual_salary,
                    'hours' => null,
                    'super_fund_name' => $fund?->fund_name, 'super_member_number' => $fund?->member_number,
                ]);
                if ($j->pay_basis === 'hourly') {
                    $this->fillHours($line, $j, $run);
                }
                $this->calculate($line, $run->frequency, (int) round(((float) $run->super_rate) * 100));
                $line->save();
            }
            $this->totals($run);
            return $run;
        });

        return response()->json(['data' => $this->full($run->fresh(), $b)], 201);
    }

    public function updateLine(Request $request, int $id, int $lineId): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        [$run, $line] = $this->findLine($b->company_id, $id, $lineId);
        if (!$run) {
            return $this->failure('Pay run not found.', 404);
        }
        if ($run->status !== 'draft') {
            return $this->failure('This pay run is finalised and can no longer be changed.', 422);
        }
        $v = Validator::make($request->all(), [
            'hours' => ['nullable', 'numeric', 'min:0', 'max:744', $this->twoDecimals('Hours')],
            'tax_override' => ['nullable', 'numeric', 'min:0', 'max:9999999.99', $this->twoDecimals('Tax')],
            'clear_tax_override' => ['nullable', 'boolean'],
            'confirm_tax' => ['nullable', 'boolean'],
            'confirm_hours' => ['nullable', 'boolean'],
        ]);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $d = $v->validated();
        if (array_key_exists('hours', $request->all())) {
            if ($line->pay_basis !== 'hourly') {
                return $this->failure('This employee is paid a salary, so there are no hours to enter.', 422);
            }
            $line->hours = $d['hours'] ?? null;
            $line->hours_source = $line->hours === null ? null : 'manual';
            $line->hours_confirmed = $line->hours !== null;
            if (!$line->tax_overridden) {
                $line->tax_confirmed = false; // the amount changed, so an earlier confirmation no longer applies
            }
        }
        if (!empty($d['clear_tax_override'])) {
            $line->tax_overridden = false;
            $line->tax_confirmed = false;
            $line->tax = 0;
        } elseif (isset($d['tax_override'])) {
            $line->tax_overridden = true;
            $line->tax = $d['tax_override'];
            $line->tax_confirmed = true;
        }
        if (!empty($d['confirm_tax'])) {
            $line->tax_confirmed = true;
        }
        if (!empty($d['confirm_hours']) && $line->hours !== null) {
            $line->hours_confirmed = true;
        }
        $this->calculate($line, $run->frequency, (int) round(((float) $run->super_rate) * 100));
        $line->save();
        $this->totals($run);
        return response()->json(['data' => $this->full($run->fresh(), $b)]);
    }

    /** Adds an allowance, bonus, overtime, reimbursement or deduction to one employee's line. */
    public function addItem(Request $request, int $id, int $lineId): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        [$run, $line] = $this->findLine($b->company_id, $id, $lineId);
        if (!$run) {
            return $this->failure('Pay run not found.', 404);
        }
        if ($run->status !== 'draft') {
            return $this->failure('This pay run is finalised and can no longer be changed.', 422);
        }
        $v = Validator::make($request->all(), [
            'kind' => ['required', Rule::in(PayRunLineItem::MANUAL)],
            'label' => ['required', 'string', 'max:60'],
            'amount' => ['required_unless:kind,overtime', 'nullable', 'numeric', 'gt:0', 'max:9999999.99', $this->twoDecimals('Amount')],
            'hours' => ['required_if:kind,overtime', 'nullable', 'numeric', 'gt:0', 'max:744', $this->twoDecimals('Hours')],
            'rate' => ['required_if:kind,overtime', 'nullable', 'numeric', 'gt:0', 'max:9999.99', $this->twoDecimals('Rate')],
            'counts_for_super' => ['nullable', 'boolean'],
        ], [
            'kind.required' => 'Choose the type of item.',
            'label.required' => 'Describe the item, for example "Phone allowance".',
            'label.max' => 'Keep the description under 60 characters.',
            'amount.required_unless' => 'Enter an amount.',
            'amount.gt' => 'The amount must be more than 0.',
            'hours.required_if' => 'Enter the overtime hours.',
            'rate.required_if' => 'Enter the overtime rate per hour.',
        ]);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $d = $v->validated();
        $kind = $d['kind'];
        $hours = $kind === 'overtime' ? (float) $d['hours'] : null;
        $rate = $kind === 'overtime' ? (float) $d['rate'] : null;
        $amountC = $kind === 'overtime' ? Calc::hourlyGrossCents($this->cents($hours), $this->cents($rate)) : $this->cents($d['amount']);
        $super = in_array($kind, ['allowance', 'bonus'], true) ? (bool) ($d['counts_for_super'] ?? true) : false;

        try {
            DB::transaction(function () use ($line, $run, $kind, $d, $hours, $rate, $amountC, $super) {
                PayRunLineItem::create([
                    'pay_run_line_id' => $line->id, 'kind' => $kind, 'label' => trim($d['label']),
                    'hours' => $hours, 'rate' => $rate, 'amount' => $amountC / 100, 'counts_for_super' => $super,
                ]);
                $this->recalc($line, $run, true);
            });
        } catch (\DomainException $e) {
            return $this->failure($e->getMessage(), 422); // the transaction was rolled back, so nothing was added
        }
        return response()->json(['data' => $this->full($run->fresh(), $b)], 201);
    }

    public function removeItem(int $id, int $lineId, int $itemId): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        [$run, $line] = $this->findLine($b->company_id, $id, $lineId);
        if (!$run) {
            return $this->failure('Pay run not found.', 404);
        }
        if ($run->status !== 'draft') {
            return $this->failure('This pay run is finalised and can no longer be changed.', 422);
        }
        $item = PayRunLineItem::where('pay_run_line_id', $line->id)->where('id', $itemId)->first();
        if (!$item) {
            return $this->failure('Item not found.', 404);
        }
        $item->delete();
        $this->recalc($line, $run, false);
        return response()->json(['data' => $this->full($run->fresh(), $b)]);
    }

    /** Recalculates a line after its items changed. With $guard, throws if take-home pay would go below zero. */
    private function recalc(PayRunLine $line, PayRun $run, bool $guard = true): void
    {
        $line = $line->fresh();
        if (!$line->tax_overridden) {
            $line->tax_confirmed = false; // the amounts changed, so an earlier confirmation no longer applies
        }
        $this->calculate($line, $run->frequency, (int) round(((float) $run->super_rate) * 100));
        if ($guard && $line->net < 0) {
            throw new \DomainException('Deductions cannot be more than the pay after tax.');
        }
        $line->save();
        $this->totals($run);
    }

    public function destroyLine(int $id, int $lineId): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        [$run, $line] = $this->findLine($b->company_id, $id, $lineId);
        if (!$run) {
            return $this->failure('Pay run not found.', 404);
        }
        if ($run->status !== 'draft') {
            return $this->failure('This pay run is finalised and can no longer be changed.', 422);
        }
        $line->delete();
        $this->totals($run);
        return response()->json(['data' => $this->full($run->fresh(), $b)]);
    }

    public function finalise(int $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        return DB::transaction(function () use ($b, $id) {
        $run = PayRun::where('company_id', $b->company_id)->where('id', $id)->lockForUpdate()->first();
        if (!$run) {
            return $this->failure('Pay run not found.', 404);
        }
        if ($run->status !== 'draft') {
            return $this->failure('This pay run is already finalised.', 422);
        }
        $lines = PayRunLine::where('pay_run_id', $run->id)->get();
        if ($lines->isEmpty() || $lines->sum('gross') <= 0) {
            return $this->failure('There is nothing to pay in this run yet.', 422);
        }
        $problems = [];
        foreach ($lines as $l) {
            if ($l->net < 0) {
                $problems[] = $l->employee_name . ': take-home pay is below zero, so check the deductions';
                continue;
            }
            if ($l->pay_basis === 'hourly' && $l->hours === null) {
                $problems[] = $l->employee_name . ': enter hours, or remove them from this run';
            } elseif ($l->pay_basis === 'hourly' && !$l->hours_confirmed) {
                $problems[] = $l->employee_name . ': check the hours, which are only an estimate from their usual week';
            } elseif ($l->tax_status !== 'auto' && !$l->tax_confirmed) {
                $problems[] = $l->employee_name . ': check and confirm the tax';
            }
        }
        if ($problems) {
            return $this->failure('Some lines need your attention before this can be finalised.', 422, ['lines' => implode('. ', $problems) . '.']);
        }
        $run->forceFill([
            'status' => 'finalised', 'finalised_at' => now(),
            'super_due_date' => $this->addBusinessDays(Carbon::parse($run->pay_date), 7)->toDateString(),
        ])->save();
        return response()->json(['data' => $this->full($run->fresh(), $b)]);
        });
    }

    /** Records (or undoes) that the employer paid the super for a finalised run. */
    public function superPaid(Request $request, int $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $run = PayRun::where('company_id', $b->company_id)->where('id', $id)->first();
        if (!$run) {
            return $this->failure('Pay run not found.', 404);
        }
        if ($run->status !== 'finalised') {
            return $this->failure('Super can be marked as paid once the pay run is finalised.', 422);
        }
        $run->forceFill(['super_paid_at' => $request->boolean('paid', true) ? now() : null])->save();
        return response()->json(['data' => $this->full($run->fresh(), $b)]);
    }

    public function destroy(int $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $run = PayRun::where('company_id', $b->company_id)->where('id', $id)->first();
        if (!$run) {
            return $this->failure('Pay run not found.', 404);
        }
        if ($run->status !== 'draft') {
            return $this->failure('A finalised pay run is a payroll record and cannot be deleted.', 422);
        }
        DB::transaction(function () use ($run) {
            PayRunLine::where('pay_run_id', $run->id)->delete();
            $run->delete();
        });
        return response()->json(['message' => 'Draft pay run deleted.']);
    }

    // ------------------------------------------------------------ calculation

    private function cents($v): int
    {
        return (int) round(((float) $v) * 100);
    }

    /** Replaces hourly lines' hours with approved timesheet hours where there are any. Returns how many lines changed. */
    public function refreshHours(int $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $run = PayRun::where('company_id', $b->company_id)->where('id', $id)->first();
        if (!$run) {
            return $this->failure('Pay run not found.', 404);
        }
        if ($run->status !== 'draft') {
            return $this->failure('This pay run is finalised and can no longer be changed.', 422);
        }
        $changed = 0;
        DB::transaction(function () use ($run, &$changed) {
            foreach (PayRunLine::where('pay_run_id', $run->id)->where('pay_basis', 'hourly')->get() as $l) {
                $h = TimesheetHours::approvedHundredths((int) $run->company_id, (int) $l->employee_id, $run->period_start->toDateString(), $run->period_end->toDateString());
                if ($h === null) {
                    continue;
                }
                $l->forceFill(['hours' => $h / 100, 'hours_source' => 'timesheet', 'hours_confirmed' => true]);
                if (!$l->tax_overridden) {
                    $l->tax_confirmed = false;
                }
                $this->calculate($l, $run->frequency, (int) round(((float) $run->super_rate) * 100));
                $l->save();
                $changed++;
            }
            $this->totals($run);
        });
        return response()->json(['data' => $this->full($run->fresh(), $b), 'updated' => $changed]);
    }

    /** Hours for a new hourly line: approved timesheets if there are any, otherwise an estimate the person must check. */
    private function fillHours(PayRunLine $line, EmploymentInformation $j, PayRun $run): void
    {
        $h = TimesheetHours::approvedHundredths((int) $run->company_id, (int) $line->employee_id, $run->period_start->toDateString(), $run->period_end->toDateString());
        if ($h !== null) {
            $line->hours = $h / 100; $line->hours_source = 'timesheet'; $line->hours_confirmed = true;
            return;
        }
        $est = $this->defaultHours($j, $run->frequency);
        $line->hours = $est; $line->hours_source = $est === null ? null : 'estimated'; $line->hours_confirmed = false;
    }

    private function defaultHours(EmploymentInformation $j, string $freq): ?float
    {
        if ($j->hours_per_week === null) {
            return null;
        }
        $h = $this->cents($j->hours_per_week); // hundredths of an hour a week
        $period = match ($freq) {
            Calc::WEEKLY => $h,
            Calc::FORTNIGHTLY => $h * 2,
            default => intdiv($h * 52 * 2 + 12, 24),   // x 52 / 12, half up
        };
        return $period / 100;
    }

    /** Works out gross, tax, super and net for a line, and says whether the tax needs a person's check. */
    private function calculate(PayRunLine $l, string $freq, int $rateHundredths): void
    {
        $grossC = 0;
        if ($l->pay_basis === 'salary') {
            $grossC = Calc::salaryForPeriodCents($this->cents($l->annual_salary), $freq);
        } elseif ($l->hours !== null) {
            $grossC = Calc::hourlyGrossCents($this->cents($l->hours), $this->cents($l->hourly_rate));
        }

        // Extra items: taxable earnings add to gross, reimbursements and deductions only change take-home pay.
        $baseC = $grossC;
        $earnC = $superEarnC = $reimbC = $dedC = 0;
        $items = $l->exists ? PayRunLineItem::where('pay_run_line_id', $l->id)->get() : collect();
        foreach ($items as $it) {
            $a = $this->cents($it->amount);
            if ($it->isTaxable()) {
                $earnC += $a;
                $superEarnC += $it->counts_for_super ? $a : 0;
            } elseif ($it->kind === 'reimbursement') {
                $reimbC += $a;
            } elseif ($it->kind === 'deduction') {
                $dedC += $a;
            }
        }
        $grossC = $baseC + $earnC;

        $tax = TaxDetail::where('employee_id', $l->employee_id)->first();
        $hasTfn = $tax && !empty($tax->getRawOriginal('tax_file_number'));
        $foreign = $tax && $tax->residency_status === 'Foreign resident';
        $whm = $tax && $tax->tax_scale_type === 'Working holiday maker';
        $loan = $tax && $tax->has_study_training_loans;

        $scale = null; $status = 'auto'; $note = null;
        if (!$hasTfn) {
            $scale = Calc::SCALE_NO_TFN;
            $note = 'No tax file number on record, so tax is withheld at the no-TFN rate.';
        } elseif ($foreign || $whm) {
            $status = 'manual_required';
            $note = $whm ? 'Working holiday maker: enter the tax to withhold.' : 'Foreign resident: enter the tax to withhold.';
        } else {
            $scale = $tax->claim_tax_free_threshold ? Calc::SCALE_THRESHOLD : Calc::SCALE_NO_THRESHOLD;
            if ($loan) {
                $status = 'review';
                $note = 'Study and training loan repayments are not included. Add them to the tax, then confirm.';
            }
        }

        if ($l->tax_overridden) {
            $taxC = $this->cents($l->tax);
            $method = 'manual';
        } else {
            $taxC = $scale ? Calc::withholding($grossC, $freq, $scale, $foreign) : 0;
            $method = $scale ?: 'manual';
            if ($status === 'auto' || $grossC === 0) {
                $l->tax_confirmed = $status === 'auto' ? false : $l->tax_confirmed;
            }
        }
        $l->base_pay = $baseC / 100;
        $l->reimbursements = $reimbC / 100;
        $l->deductions = $dedC / 100;
        $l->gross = $grossC / 100;
        $l->tax = $taxC / 100;
        $l->tax_method = $method;
        $l->tax_status = $status;
        $l->tax_note = $note;
        $l->super_amount = Calc::superCents($baseC + $superEarnC, $rateHundredths) / 100;
        $l->net = ($grossC - $taxC + $reimbC - $dedC) / 100;
    }

    private function totals(PayRun $run): void
    {
        $lines = PayRunLine::where('pay_run_id', $run->id)->get();
        $run->forceFill([
            'total_gross' => $this->cents($lines->sum('gross')) / 100,
            'total_tax' => $this->cents($lines->sum('tax')) / 100,
            'total_super' => $this->cents($lines->sum('super_amount')) / 100,
            'total_net' => $this->cents($lines->sum('net')) / 100,
        ])->save();
    }

    /** Super must reach the fund within 7 business days of payday. Public holidays are not counted here, so pay earlier if one falls in between. */
    private function addBusinessDays(Carbon $date, int $days): Carbon
    {
        $d = $date->copy();
        while ($days > 0) {
            $d->addDay();
            if (!$d->isWeekend()) {
                $days--;
            }
        }
        return $d;
    }

    private function findLine(int $companyId, int $id, int $lineId): array
    {
        $run = PayRun::where('company_id', $companyId)->where('id', $id)->first();
        $line = $run ? PayRunLine::where('pay_run_id', $run->id)->where('id', $lineId)->first() : null;
        return $line ? [$run, $line] : [null, null];
    }

    // ------------------------------------------------------------ output

    private function row(PayRun $r, int $employees): array
    {
        return [
            'id' => $r->id, 'reference' => $r->reference, 'frequency' => $r->frequency,
            'period_start' => $r->period_start->toDateString(), 'period_end' => $r->period_end->toDateString(),
            'pay_date' => $r->pay_date->toDateString(), 'status' => $r->status, 'employees' => $employees,
            'total_gross' => $this->money($r->total_gross), 'total_tax' => $this->money($r->total_tax),
            'total_super' => $this->money($r->total_super), 'total_net' => $this->money($r->total_net),
        ];
    }

    private function full(PayRun $r, $business): array
    {
        $lines = PayRunLine::where('pay_run_id', $r->id)->orderBy('employee_name')->get();
        $needs = $lines->filter(fn (PayRunLine $l) => $l->needsAttention())->count();
        $draft = $r->status === 'draft';
        $items = PayRunLineItem::whereIn('pay_run_line_id', $lines->pluck('id'))->orderBy('id')->get()->groupBy('pay_run_line_id');
        $ytd = $draft ? [] : $this->yearToDate($r);

        return $this->row($r, $lines->count()) + [
            'super_due_date' => $r->super_due_date?->toDateString(),
            'super_paid_at' => $r->super_paid_at ? Carbon::parse($r->super_paid_at)->toIso8601String() : null,
            'finalised_at' => $r->finalised_at?->toIso8601String(),
            'employer' => $this->presentSeller($business), 'super_rate' => (float) $r->super_rate,
            'needs_attention' => $needs,
            'can' => ['edit' => $draft, 'finalise' => $draft && $lines->count() > 0, 'delete' => $draft],
            'lines' => $lines->map(fn (PayRunLine $l) => [
                'id' => $l->id, 'employee_id' => $l->employee_id, 'employee_name' => $l->employee_name,
                'pay_basis' => $l->pay_basis,
                'hourly_rate' => $l->hourly_rate !== null ? (float) $l->hourly_rate : null,
                'annual_salary' => $l->annual_salary !== null ? (float) $l->annual_salary : null,
                'hours' => $l->hours !== null ? (float) $l->hours : null,
                'base_pay' => $this->money($l->base_pay), 'reimbursements' => $this->money($l->reimbursements), 'deductions' => $this->money($l->deductions),
                'items' => ($items->get($l->id) ?? collect())->map(fn ($i) => [
                    'id' => $i->id, 'kind' => $i->kind, 'label' => $i->label,
                    'hours' => $i->hours !== null ? (float) $i->hours : null, 'rate' => $i->rate !== null ? (float) $i->rate : null,
                    'amount' => $this->money($i->amount), 'counts_for_super' => (bool) $i->counts_for_super,
                ])->values(),
                'hours_source' => $l->hours_source, 'hours_confirmed' => (bool) $l->hours_confirmed,
                'gross' => $this->money($l->gross), 'tax' => $this->money($l->tax),
                'super_amount' => $this->money($l->super_amount), 'net' => $this->money($l->net),
                'tax_method' => $l->tax_method, 'tax_status' => $l->tax_status, 'tax_note' => $l->tax_note,
                'tax_overridden' => (bool) $l->tax_overridden, 'tax_confirmed' => (bool) $l->tax_confirmed,
                'super_fund_name' => $l->super_fund_name, 'super_member_number' => $l->super_member_number,
                'ytd' => $ytd[$l->employee_id] ?? null,
            ])->values(),
        ];
    }

    /**
     * Totals so far in this financial year (1 July to 30 June) for each employee in a finalised run,
     * up to and including this run. Only finalised runs count.
     */
    private function yearToDate(PayRun $r): array
    {
        $pay = Carbon::parse($r->pay_date);
        $fyStart = Carbon::create($pay->month >= 7 ? $pay->year : $pay->year - 1, 7, 1)->toDateString();
        $ids = PayRunLine::where('pay_run_id', $r->id)->pluck('employee_id');
        $rows = DB::table('pay_run_lines as l')->join('pay_runs as x', 'x.id', '=', 'l.pay_run_id')
            ->where('x.company_id', $r->company_id)->where('x.status', 'finalised')->whereIn('l.employee_id', $ids)
            ->whereDate('x.pay_date', '>=', $fyStart)
            ->where(function ($q) use ($r) {
                $q->whereDate('x.pay_date', '<', $r->pay_date->toDateString())
                  ->orWhere(fn ($q2) => $q2->whereDate('x.pay_date', $r->pay_date->toDateString())->where('x.id', '<=', $r->id));
            })->get(['l.employee_id', 'l.gross', 'l.tax', 'l.super_amount', 'l.net']);
        $out = [];
        foreach ($rows as $x) {
            $o = &$out[$x->employee_id];
            $o ??= ['gross' => 0, 'tax' => 0, 'super' => 0, 'net' => 0];
            $o['gross'] += $this->cents($x->gross); $o['tax'] += $this->cents($x->tax);
            $o['super'] += $this->cents($x->super_amount); $o['net'] += $this->cents($x->net);
            unset($o);
        }
        return array_map(fn ($o) => array_map(fn ($v) => $v / 100, $o), $out);
    }
}
