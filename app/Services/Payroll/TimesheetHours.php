<?php

namespace App\Services\Payroll;

use App\Models\TimesheetEntry;
use App\Models\TimesheetWeek;
use Carbon\Carbon;

/** Turns approved timesheet weeks into the hours for a pay period. */
class TimesheetHours
{
    public static function weekStart($date): Carbon
    {
        return Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /**
     * Hours worked in [start, end] from APPROVED weeks only, in hundredths of an hour.
     * Returns null when no approved week touches the period, meaning there is nothing to use yet.
     */
    public static function approvedHundredths(int $companyId, int $employeeId, string $start, string $end): ?int
    {
        $from = self::weekStart($start)->toDateString();
        $weeks = TimesheetWeek::where('company_id', $companyId)->where('employee_id', $employeeId)
            ->where('status', 'approved')->whereDate('week_start', '>=', $from)->whereDate('week_start', '<=', $end)
            ->get()->map(fn ($w) => $w->week_start->toDateString())->flip();
        if ($weeks->isEmpty()) {
            return null;
        }
        $total = 0;
        $entries = TimesheetEntry::where('company_id', $companyId)->where('employee_id', $employeeId)
            ->whereDate('work_date', '>=', $start)->whereDate('work_date', '<=', $end)->get();
        foreach ($entries as $e) {
            if (isset($weeks[self::weekStart($e->work_date)->toDateString()])) {
                $total += (int) round($e->hours * 100);
            }
        }
        return $total;
    }
}
