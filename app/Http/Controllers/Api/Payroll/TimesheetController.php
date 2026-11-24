<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\Employee;
use App\Models\EmploymentInformation;
use App\Models\PayRun;
use App\Models\TimesheetEntry;
use App\Models\TimesheetWeek;
use App\Services\Payroll\TimesheetHours;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Weekly timesheets for hourly employees of the business being worked in. */
class TimesheetController extends Controller
{
    use SalesSupport;

    /** One week (Monday to Sunday): every current hourly employee with their hours for each day. */
    public function index(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $date = (string) $request->input('week', Carbon::today()->toDateString());
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            return $this->failure('Choose a valid week.', 422);
        }
        $start = TimesheetHours::weekStart($date);
        $end = $start->copy()->addDays(6);
        $days = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i)->toDateString())->all();

        $people = Employee::where('emp_company_id', $b->company_id)->where('emp_status', 'Active')->get();
        $jobs = EmploymentInformation::whereIn('employee_id', $people->pluck('emp_id'))->where('pay_basis', 'hourly')->get()->keyBy('employee_id');
        $hourly = $people->filter(fn ($e) => $jobs->has($e->emp_id))
            ->sortBy(fn ($e) => strtolower($e->emp_first_name . ' ' . $e->emp_last_name))->values();
        $ids = $hourly->pluck('emp_id');

        $entries = TimesheetEntry::where('company_id', $b->company_id)->whereIn('employee_id', $ids)
            ->whereDate('work_date', '>=', $start->toDateString())->whereDate('work_date', '<=', $end->toDateString())->get()->groupBy('employee_id');
        $weeks = TimesheetWeek::where('company_id', $b->company_id)->whereIn('employee_id', $ids)
            ->whereDate('week_start', $start->toDateString())->get()->keyBy('employee_id');

        return response()->json(['data' => [
            'week_start' => $start->toDateString(), 'week_end' => $end->toDateString(), 'days' => $days,
            'employees' => $hourly->map(function (Employee $e) use ($entries, $weeks, $days, $jobs, $b, $start, $end) {
                $byDate = ($entries->get($e->emp_id) ?? collect())->keyBy(fn ($x) => $x->work_date->toDateString());
                $hours = [];
                $total = 0;
                foreach ($days as $d) {
                    $h = $byDate->has($d) ? (float) $byDate[$d]->hours : null;
                    $hours[$d] = $h;
                    $total += $h ? (int) round($h * 100) : 0;
                }
                $locked = $this->paidIn($b->company_id, $e->emp_id, $start, $end);
                $w = $weeks->get($e->emp_id);
                return [
                    'employee_id' => (int) $e->emp_id, 'name' => trim($e->emp_first_name . ' ' . $e->emp_last_name),
                    'hourly_rate' => $jobs[$e->emp_id]->hourly_rate !== null ? (float) $jobs[$e->emp_id]->hourly_rate : null,
                    'hours' => $hours, 'total' => $total / 100,
                    'status' => $w?->status ?? 'draft', 'locked_in' => $locked,
                ];
            })->values(),
        ]]);
    }

    /** Saves the hours for one employee for one week. */
    public function save(Request $request, int $employeeId): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $start = $this->weekFrom($request);
        if (!$start) {
            return $this->failure('Choose a valid week.', 422);
        }
        if (!$this->hourlyEmployee($b->company_id, $employeeId)) {
            return $this->failure('Timesheets are for current employees who are paid by the hour.', 404);
        }
        $end = $start->copy()->addDays(6);
        $hours = $request->input('hours');
        if (!is_array($hours)) {
            return $this->failure('Enter the hours for each day.', 422);
        }
        $errors = [];
        $clean = [];
        foreach ($hours as $date => $h) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) || Carbon::parse($date)->lt($start) || Carbon::parse($date)->gt($end)) {
                return $this->failure('Those hours are not in this week.', 422);
            }
            if ($h === null || $h === '') {
                $clean[$date] = null;
                continue;
            }
            if (!is_numeric($h) || $h < 0 || $h > 24) {
                $errors['hours.' . $date] = 'Enter hours between 0 and 24.';
            } elseif (abs($h * 100 - round($h * 100)) > 1e-6) {
                $errors['hours.' . $date] = 'Hours can have at most 2 decimal places.';
            } else {
                $clean[$date] = round((float) $h, 2);
            }
        }
        if ($errors) {
            return $this->failure('Please fix the highlighted hours.', 422, $errors);
        }
        if ($ref = $this->paidIn($b->company_id, $employeeId, $start, $end)) {
            return $this->failure('These hours are already paid in ' . $ref . ' and cannot be changed.', 422);
        }
        $week = TimesheetWeek::where('company_id', $b->company_id)->where('employee_id', $employeeId)->whereDate('week_start', $start->toDateString())->first();
        if ($week && $week->status === 'approved') {
            return $this->failure('This week is approved. Reopen it to change the hours.', 422);
        }
        DB::transaction(function () use ($b, $employeeId, $clean, $start) {
            foreach ($clean as $date => $h) {
                $q = TimesheetEntry::where('employee_id', $employeeId)->whereDate('work_date', $date);
                if ($h === null) {
                    $q->delete();
                } else {
                    TimesheetEntry::updateOrCreate(['employee_id' => $employeeId, 'work_date' => $date], ['company_id' => $b->company_id, 'hours' => $h]);
                }
            }
            TimesheetWeek::firstOrCreate(['employee_id' => $employeeId, 'week_start' => $start->toDateString()], ['company_id' => $b->company_id, 'status' => 'draft']);
        });
        return $this->index($request->merge(['week' => $start->toDateString()]));
    }

    /** Approves a week so its hours flow into pay runs, or reopens it. */
    public function approve(Request $request, int $employeeId): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $start = $this->weekFrom($request);
        if (!$start) {
            return $this->failure('Choose a valid week.', 422);
        }
        if (!$this->hourlyEmployee($b->company_id, $employeeId)) {
            return $this->failure('Timesheets are for current employees who are paid by the hour.', 404);
        }
        $approve = $request->boolean('approved', true);
        if ($ref = $this->paidIn($b->company_id, $employeeId, $start, $start->copy()->addDays(6))) {
            return $this->failure('These hours are already paid in ' . $ref . ' and cannot be changed.', 422);
        }
        if ($approve) {
            $has = TimesheetEntry::where('company_id', $b->company_id)->where('employee_id', $employeeId)
                ->whereDate('work_date', '>=', $start->toDateString())->whereDate('work_date', '<=', $start->copy()->addDays(6)->toDateString())->exists();
            if (!$has) {
                return $this->failure('Enter some hours before approving this week.', 422);
            }
        }
        TimesheetWeek::updateOrCreate(
            ['employee_id' => $employeeId, 'week_start' => $start->toDateString()],
            ['company_id' => $b->company_id, 'status' => $approve ? 'approved' : 'draft',
             'approved_at' => $approve ? now() : null, 'approved_by' => $approve ? auth()->guard('api')->id() : null]
        );
        return $this->index($request->merge(['week' => $start->toDateString()]));
    }

    private function weekFrom(Request $request): ?Carbon
    {
        $d = (string) $request->input('week_start');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) ? TimesheetHours::weekStart($d) : null;
    }

    private function hourlyEmployee(int $companyId, int $employeeId): bool
    {
        $e = Employee::where('emp_company_id', $companyId)->where('emp_id', $employeeId)->where('emp_status', 'Active')->first();
        return $e && EmploymentInformation::where('employee_id', $employeeId)->where('pay_basis', 'hourly')->exists();
    }

    /** The reference of a finalised pay run that already paid this employee for any of these days, if one did. */
    private function paidIn(int $companyId, int $employeeId, Carbon $start, Carbon $end): ?string
    {
        return PayRun::where('company_id', $companyId)->where('status', 'finalised')
            ->whereDate('period_start', '<=', $end->toDateString())->whereDate('period_end', '>=', $start->toDateString())
            ->whereHas('lines', fn ($q) => $q->where('employee_id', $employeeId))->orderBy('id')->value('reference');
    }
}
