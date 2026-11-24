<?php

namespace App\Services\Payroll;

use App\Models\EmploymentInformation;
use App\Models\LeaveEntry;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayRun;
use App\Models\PayRunLine;
use App\Models\PayRunLineItem;
use App\Models\PayrollSetting;
use App\Services\Payroll\PayCalculator as Calc;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Leave rules and balances. Hours are handled as whole hundredths of an hour (7.60 hours = 760) so nothing drifts. */
class LeaveService
{
    /** The business's leave types, created from the defaults the first time they are needed. */
    public static function types(int $companyId): Collection
    {
        $have = LeaveType::where('company_id', $companyId)->get();
        foreach (LeaveType::DEFAULTS as $d) {
            if (!$have->contains('code', $d['code'])) {
                LeaveType::create($d + ['company_id' => $companyId]);
            }
        }
        return LeaveType::where('company_id', $companyId)->orderBy('id')->get();
    }

    public static function hundredths($hours): int
    {
        return (int) round(((float) $hours) * 100);
    }

    /** Balances in hours: [employee_id][leave_type_id] => hours. */
    public static function balances(int $companyId, $employeeIds): array
    {
        $out = [];
        $rows = LeaveEntry::where('company_id', $companyId)->whereIn('employee_id', $employeeIds)
            ->selectRaw('employee_id, leave_type_id, SUM(ROUND(hours * 100)) as h')->groupBy('employee_id', 'leave_type_id')->get();
        foreach ($rows as $r) {
            $out[(int) $r->employee_id][(int) $r->leave_type_id] = ((int) $r->h) / 100;
        }
        return $out;
    }

    public static function isCasual(?EmploymentInformation $job): bool
    {
        return $job && $job->employment_basis === 'Casual';
    }

    /** An employee's usual hours in one day: their weekly hours over 5 days, else the business default. */
    public static function dailyHoursHundredths(int $companyId, ?EmploymentInformation $job): int
    {
        $weekly = $job && $job->hours_per_week ? (float) $job->hours_per_week : PayrollSetting::forCompany($companyId)['default_hours_per_week'];
        return intdiv(self::hundredths($weekly) * 2 + 5, 10);   // weekly / 5, rounded half up
    }

    public static function weekdays(Carbon $start, Carbon $end): int
    {
        $n = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $n += $d->isWeekday() ? 1 : 0;
        }
        return $n;
    }

    /**
     * Adds the leave an employee earned in a pay run that has just been finalised.
     * Casual employees do not build up leave. Each employee is only credited once per run.
     */
    public static function accrueForRun(PayRun $run): void
    {
        $types = self::types($run->company_id)->filter(fn ($t) => $t->paid && $t->accrual_hours);
        if ($types->isEmpty()) {
            return;
        }
        $lines = PayRunLine::where('pay_run_id', $run->id)->get();
        $jobs = EmploymentInformation::whereIn('employee_id', $lines->pluck('employee_id'))->get()->keyBy('employee_id');
        $leaveHours = PayRunLineItem::whereIn('pay_run_line_id', $lines->pluck('id'))->where('kind', 'leave')
            ->selectRaw('pay_run_line_id, SUM(ROUND(hours * 100)) as h')->groupBy('pay_run_line_id')->pluck('h', 'pay_run_line_id');
        $defaultWeekly = PayrollSetting::forCompany($run->company_id)['default_hours_per_week'];

        foreach ($lines as $l) {
            $job = $jobs->get($l->employee_id);
            if (self::isCasual($job)) {
                continue;
            }
            if ($l->pay_basis === 'hourly') {
                $worked = self::hundredths($l->hours) + (int) ($leaveHours[$l->id] ?? 0);   // paid leave counts as time worked
            } else {
                $weekly = self::hundredths($job && $job->hours_per_week ? $job->hours_per_week : $defaultWeekly);
                $worked = match ($run->frequency) {
                    Calc::WEEKLY => $weekly,
                    Calc::FORTNIGHTLY => $weekly * 2,
                    default => intdiv($weekly * 52 * 2 + 12, 24),   // monthly: 52 weeks / 12, rounded half up
                };
            }
            foreach ($types as $t) {
                $n = self::hundredths($t->accrual_hours);
                $earned = intdiv($worked * 200 + $n, 2 * $n);   // worked / n, rounded half up
                $already = LeaveEntry::where('kind', 'accrual')->where('source_id', $run->id)->where('employee_id', $l->employee_id)->where('leave_type_id', $t->id)->exists();
                if ($earned > 0 && !$already) {
                    LeaveEntry::create([
                        'company_id' => $run->company_id, 'employee_id' => $l->employee_id, 'leave_type_id' => $t->id,
                        'kind' => 'accrual', 'hours' => $earned / 100, 'entry_date' => $run->pay_date->toDateString(),
                        'note' => 'Built up in ' . $run->reference, 'source_id' => $run->id,
                    ]);
                }
            }
        }
    }

    /** Approved paid leave that has not been put on a pay run yet, for the given employees. */
    public static function unpaidApproved(int $companyId, $employeeIds): Collection
    {
        $paidTypes = LeaveType::where('company_id', $companyId)->where('paid', true)->pluck('id');
        $reqs = LeaveRequest::where('company_id', $companyId)->where('status', 'approved')
            ->whereIn('employee_id', $employeeIds)->whereIn('leave_type_id', $paidTypes)->orderBy('start_date')->get();
        $onRun = PayRunLineItem::where('kind', 'leave')->whereIn('source_id', $reqs->pluck('id'))
            ->whereIn('pay_run_line_id', PayRunLine::select('id'))->pluck('source_id')->all();
        return $reqs->reject(fn ($r) => in_array($r->id, $onRun, true))->values();
    }

    /** The pay run a leave request was paid in, if it was. */
    public static function paidIn(int $requestId): ?string
    {
        $lineId = PayRunLineItem::where('kind', 'leave')->where('source_id', $requestId)->whereIn('pay_run_line_id', PayRunLine::select('id'))->value('pay_run_line_id');
        $line = $lineId ? PayRunLine::find($lineId) : null;
        return $line ? (PayRun::where('id', $line->pay_run_id)->value('reference') ?: 'a pay run') : null;
    }
}
