<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sales\Concerns\SalesSupport;
use App\Models\Employee;
use App\Models\EmploymentInformation;
use App\Models\LeaveEntry;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Payroll\LeaveService as Leave;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Leave for the business being worked in: balances, requests, approvals and adjustments. */
class LeaveController extends Controller
{
    use SalesSupport;

    /** Leave types and every current employee's balance of each. */
    public function index(): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        return response()->json(['data' => $this->overview($b->company_id)]);
    }

    /** Changes how fast a paid leave type builds up. */
    public function updateType(Request $request, int $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $type = LeaveType::where('company_id', $b->company_id)->where('id', $id)->first();
        if (!$type) {
            return $this->failure('Leave type not found.', 404);
        }
        if (!$type->paid) {
            return $this->failure('Unpaid leave does not build up.', 422);
        }
        $v = Validator::make($request->all(), [
            'accrual_hours' => ['nullable', 'numeric', 'gte:1', 'max:1000', $this->twoDecimals('Hours')],
        ], ['accrual_hours.gte' => 'Enter at least 1 hour, or leave it empty if this leave does not build up.', 'accrual_hours.numeric' => 'Enter a number of hours.']);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $type->update(['accrual_hours' => $request->input('accrual_hours') !== null && $request->input('accrual_hours') !== '' ? (float) $request->input('accrual_hours') : null]);
        return response()->json(['data' => $this->overview($b->company_id)]);
    }

    public function requests(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $q = LeaveRequest::where('company_id', $b->company_id);
        if (in_array($request->input('status'), ['pending', 'approved', 'declined'], true)) {
            $q->where('status', $request->input('status'));
        }
        $rows = $q->orderByRaw("FIELD(status, 'pending', 'approved', 'declined')")->orderByDesc('start_date')->limit(200)->get();
        return response()->json(['data' => $this->presentRequests($b->company_id, $rows)]);
    }

    public function storeRequest(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $v = Validator::make($request->all(), [
            'employee_id' => ['required', 'integer'],
            'leave_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'hours' => ['nullable', 'numeric', 'gt:0', 'max:2000', $this->twoDecimals('Hours')],
            'note' => ['nullable', 'string', 'max:200'],
        ], [
            'employee_id.required' => 'Choose an employee.', 'leave_type_id.required' => 'Choose the type of leave.',
            'start_date.required' => 'Enter the first day.', 'end_date.required' => 'Enter the last day.',
            'end_date.after_or_equal' => 'The last day cannot be before the first day.',
            'start_date.date_format' => 'Enter the date as day, month and year.', 'end_date.date_format' => 'Enter the date as day, month and year.',
            'hours.gt' => 'Hours must be more than 0.', 'note.max' => 'Keep the note under 200 characters.',
        ]);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $d = $v->validated();
        $emp = Employee::where('emp_company_id', $b->company_id)->where('emp_id', $d['employee_id'])->where('emp_status', 'Active')->first();
        if (!$emp) {
            return $this->failure('Choose a current employee.', 422, ['employee_id' => 'Choose a current employee.']);
        }
        $type = LeaveType::where('company_id', $b->company_id)->where('id', $d['leave_type_id'])->first();
        if (!$type) {
            return $this->failure('Choose a type of leave.', 422, ['leave_type_id' => 'Choose a type of leave.']);
        }
        $job = EmploymentInformation::where('employee_id', $emp->emp_id)->first();
        if ($type->paid && Leave::isCasual($job)) {
            return $this->failure('Casual employees do not get paid leave. Choose unpaid leave instead.', 422, ['leave_type_id' => 'Casual employees do not get paid leave.']);
        }
        $start = Carbon::parse($d['start_date']);
        $end = Carbon::parse($d['end_date']);
        $clash = LeaveRequest::where('company_id', $b->company_id)->where('employee_id', $emp->emp_id)->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end->toDateString())->whereDate('end_date', '>=', $start->toDateString())->exists();
        if ($clash) {
            return $this->failure('This employee already has leave on some of these days.', 422, ['start_date' => 'Overlaps other leave for this employee.']);
        }
        if (isset($d['hours']) && $d['hours'] !== null) {
            $hoursH = Leave::hundredths($d['hours']);
        } else {
            $hoursH = Leave::weekdays($start, $end) * Leave::dailyHoursHundredths($b->company_id, $job);
            if ($hoursH <= 0) {
                return $this->failure('Those days have no working hours. Enter the hours.', 422, ['hours' => 'Enter the hours.']);
            }
        }
        $req = LeaveRequest::create([
            'company_id' => $b->company_id, 'employee_id' => $emp->emp_id, 'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'hours' => $hoursH / 100,
            'status' => 'pending', 'note' => $d['note'] ?? null,
        ]);
        return response()->json(['data' => $this->presentRequests($b->company_id, collect([$req]))[0]], 201);
    }

    /** Approves a request. Paid leave comes off the balance; pass allow_negative to approve more than the balance. */
    public function approve(Request $request, int $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        return DB::transaction(function () use ($b, $request, $id) {
            $req = LeaveRequest::where('company_id', $b->company_id)->where('id', $id)->lockForUpdate()->first();
            if (!$req) {
                return $this->failure('Leave request not found.', 404);
            }
            if ($req->status !== 'pending') {
                return $this->failure('Only a waiting request can be approved.', 422);
            }
            $type = LeaveType::find($req->leave_type_id);
            if ($type->paid) {
                $bal = Leave::balances($b->company_id, [$req->employee_id])[$req->employee_id][$type->id] ?? 0;
                if (Leave::hundredths($bal) < Leave::hundredths($req->hours) && !$request->boolean('allow_negative')) {
                    return $this->failure('Not enough ' . strtolower($type->name) . ': ' . $bal . ' hours available, ' . $req->hours . ' requested.', 422, ['balance' => 'Not enough balance.']);
                }
                LeaveEntry::create([
                    'company_id' => $b->company_id, 'employee_id' => $req->employee_id, 'leave_type_id' => $type->id,
                    'kind' => 'taken', 'hours' => -$req->hours, 'entry_date' => $req->start_date->toDateString(),
                    'note' => $type->name . ' ' . $req->start_date->format('j M') . ' to ' . $req->end_date->format('j M Y'), 'source_id' => $req->id,
                ]);
            }
            $req->forceFill(['status' => 'approved', 'decided_at' => now(), 'decided_by' => auth()->guard('api')->id()])->save();
            return response()->json(['data' => $this->presentRequests($b->company_id, collect([$req]))[0]]);
        });
    }

    public function decline(int $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $req = LeaveRequest::where('company_id', $b->company_id)->where('id', $id)->first();
        if (!$req) {
            return $this->failure('Leave request not found.', 404);
        }
        if ($req->status !== 'pending') {
            return $this->failure('Only a waiting request can be declined.', 422);
        }
        $req->forceFill(['status' => 'declined', 'decided_at' => now(), 'decided_by' => auth()->guard('api')->id()])->save();
        return response()->json(['data' => $this->presentRequests($b->company_id, collect([$req]))[0]]);
    }

    /** Deletes a request. Approved leave goes back onto the balance, unless it was already paid in a pay run. */
    public function destroy(int $id): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $req = LeaveRequest::where('company_id', $b->company_id)->where('id', $id)->first();
        if (!$req) {
            return $this->failure('Leave request not found.', 404);
        }
        if ($ref = Leave::paidIn($req->id)) {
            return $this->failure('This leave was paid in ' . $ref . ' and cannot be removed.', 422);
        }
        DB::transaction(function () use ($req) {
            LeaveEntry::where('kind', 'taken')->where('source_id', $req->id)->delete();
            $req->delete();
        });
        return response()->json(['message' => 'Leave request removed.']);
    }

    /** Adds or takes hours off a balance by hand, for example an opening balance. */
    public function adjust(Request $request): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $v = Validator::make($request->all(), [
            'employee_id' => ['required', 'integer'],
            'leave_type_id' => ['required', 'integer'],
            'hours' => ['required', 'numeric', 'not_in:0', 'between:-5000,5000', $this->twoDecimals('Hours')],
            'note' => ['required', 'string', 'max:120'],
        ], [
            'hours.required' => 'Enter the hours to add, or a minus number to take off.', 'hours.not_in' => 'Enter more or less than 0 hours.',
            'note.required' => 'Say why, for example "Opening balance".',
        ]);
        if ($v->fails()) {
            return $this->failure('Please fix the highlighted fields.', 422, $v->errors()->toArray());
        }
        $d = $v->validated();
        if (Leave::hundredths($d['hours']) === 0) {
            return $this->failure('Enter more or less than 0 hours.', 422, ['hours' => 'Enter more or less than 0 hours.']);
        }
        $emp = Employee::where('emp_company_id', $b->company_id)->where('emp_id', $d['employee_id'])->where('emp_status', 'Active')->first();
        $type = LeaveType::where('company_id', $b->company_id)->where('id', $d['leave_type_id'])->where('paid', true)->first();
        if (!$emp || !$type) {
            return $this->failure('Choose a current employee and a paid leave type.', 422);
        }
        LeaveEntry::create([
            'company_id' => $b->company_id, 'employee_id' => $emp->emp_id, 'leave_type_id' => $type->id,
            'kind' => 'adjustment', 'hours' => Leave::hundredths($d['hours']) / 100, 'entry_date' => Carbon::today()->toDateString(), 'note' => trim($d['note']),
        ]);
        return response()->json(['data' => $this->overview($b->company_id)], 201);
    }

    /** The history behind one employee's balances, newest first. */
    public function ledger(int $employeeId): JsonResponse
    {
        if (!$b = $this->business()) {
            return $this->noBusiness();
        }
        $types = Leave::types($b->company_id)->keyBy('id');
        $rows = LeaveEntry::where('company_id', $b->company_id)->where('employee_id', $employeeId)->orderByDesc('entry_date')->orderByDesc('id')->limit(100)->get();
        return response()->json(['data' => $rows->map(fn ($e) => [
            'id' => $e->id, 'date' => $e->entry_date->toDateString(), 'type' => $types[$e->leave_type_id]->name ?? 'Leave',
            'kind' => $e->kind, 'hours' => (float) $e->hours, 'note' => $e->note,
        ])->values()]);
    }

    // ------------------------------------------------------------ output

    private function overview(int $companyId): array
    {
        $types = Leave::types($companyId);
        $people = Employee::where('emp_company_id', $companyId)->where('emp_status', 'Active')->get()
            ->sortBy(fn ($e) => strtolower($e->emp_first_name . ' ' . $e->emp_last_name))->values();
        $jobs = EmploymentInformation::whereIn('employee_id', $people->pluck('emp_id'))->get()->keyBy('employee_id');
        $bal = Leave::balances($companyId, $people->pluck('emp_id'));
        return [
            'types' => $types->map(fn ($t) => ['id' => $t->id, 'code' => $t->code, 'name' => $t->name, 'paid' => (bool) $t->paid, 'accrual_hours' => $t->accrual_hours])->values(),
            'employees' => $people->map(fn ($e) => [
                'employee_id' => (int) $e->emp_id, 'name' => trim($e->emp_first_name . ' ' . $e->emp_last_name),
                'casual' => Leave::isCasual($jobs->get($e->emp_id)),
                'balances' => (object) $types->filter(fn ($t) => $t->paid)->mapWithKeys(fn ($t) => [$t->id => (float) ($bal[$e->emp_id][$t->id] ?? 0)])->all(),
            ])->values(),
            'pending' => LeaveRequest::where('company_id', $companyId)->where('status', 'pending')->count(),
        ];
    }

    private function presentRequests(int $companyId, $rows): array
    {
        $types = Leave::types($companyId)->keyBy('id');
        $names = Employee::whereIn('emp_id', $rows->pluck('employee_id'))->get()->keyBy('emp_id');
        $jobs = EmploymentInformation::whereIn('employee_id', $rows->pluck('employee_id'))->get()->keyBy('employee_id');
        $toPay = Leave::unpaidApproved($companyId, $rows->pluck('employee_id'))->pluck('id')->all();
        return $rows->map(function ($r) use ($types, $names, $jobs, $toPay) {
            $e = $names->get($r->employee_id);
            $t = $types->get($r->leave_type_id);
            return [
                'id' => $r->id, 'employee_id' => (int) $r->employee_id,
                'employee_name' => $e ? trim($e->emp_first_name . ' ' . $e->emp_last_name) : 'Former employee',
                'leave_type_id' => (int) $r->leave_type_id, 'type' => $t?->name ?? 'Leave', 'paid' => (bool) ($t?->paid),
                'start_date' => $r->start_date->toDateString(), 'end_date' => $r->end_date->toDateString(),
                'hours' => (float) $r->hours, 'status' => $r->status, 'note' => $r->note,
                // Hourly staff are paid for approved leave through a pay run. Salaried staff are paid their salary as usual.
                'to_pay' => in_array($r->id, $toPay, true) && ($jobs->get($r->employee_id)->pay_basis ?? null) === 'hourly',
            ];
        })->values()->all();
    }
}
