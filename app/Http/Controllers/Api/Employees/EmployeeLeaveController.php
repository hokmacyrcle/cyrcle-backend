<?php

namespace App\Http\Controllers\Api\Employees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\MinimalLeaveRequest;
use App\Models\EmpAssignLeaveType;
use App\Models\Dropdown;
use App\Models\Employee;
use App\Models\Company;
use App\Models\EmploymentInformation;
use Illuminate\Support\Facades\Auth;

class EmployeeLeaveController extends Controller
{
    /**
     * Store a new minimal leave request
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,emp_id',
            'leave_type_id' => 'required|integer',
            'description' => 'nullable|string',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'pay_period_hours' => 'nullable|numeric|min:0',
            'approved' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Company scoping via token
        $user = Auth::user();
        $companyId = $user?->getCompanyIdFromToken();

        // Derive pay_period_hours from emp_assign_leave_type
        $data = $validator->validated();

        try {
            // Ensure employee belongs to the token's company if company available
            if ($companyId) {
                $belongs = Employee::where('emp_id', $data['employee_id'])
                    ->where('emp_company_id', $companyId)
                    ->exists();
                if (!$belongs) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Employee does not belong to your company',
                    ], 403);
                }
            }

            // Find dropdown record for provided leave_type_id
            $leaveTypeDropdown = Dropdown::where('type', 'leave_type')
                ->where('id', $data['leave_type_id'])
                ->first();

            // Persist human-readable name to leave_requests table
            if ($leaveTypeDropdown) {
                $data['leave_type'] = $leaveTypeDropdown->name;
            }

            // Find assignment for this employee and leave type (scoped by company when available)
            $assignmentQuery = EmpAssignLeaveType::where('employee_id', $data['employee_id']);
            if ($companyId) {
                $assignmentQuery->where('company_id', $companyId);
            }
            if ($leaveTypeDropdown) {
                $assignmentQuery->where('leave_type_id', $leaveTypeDropdown->id);
            }
            $assignment = $assignmentQuery->first();

            // Compute pay period hours
            $calculatedHours = 0.0;
            if ($assignment) {
                $methodName = optional(Dropdown::find($assignment->leave_calculation_method_id))->name;
                switch ($methodName) {
                    case 'Fixed Amount Each Period':
                        // Use opening balance as a fixed period amount if set, else fallback
                        $calculatedHours = (float)($assignment->openning_balance ?? 0);
                        break;
                    case 'Manually Recorded Rate':
                        // Trust provided value if present; otherwise 0
                        $calculatedHours = isset($data['pay_period_hours']) ? (float)$data['pay_period_hours'] : 0.0;
                        break;
                    case 'No Calculation Required':
                        $calculatedHours = 0.0;
                        break;
                    case 'Based on Ordinary Earnings':
                        // Prefer stored fortnightly hours; fallback to annual/26
                        if (!is_null($assignment->hours_employee_works_fortnightly)) {
                            $calculatedHours = (float)$assignment->hours_employee_works_fortnightly;
                        } else {
                            $calculatedHours = round(((float)($assignment->hours_accured_annually ?? 0)) / 26, 2);
                        }
                        break;
                    default:
                        // Sensible fallback if unknown method
                        $calculatedHours = isset($data['pay_period_hours']) ? (float)$data['pay_period_hours'] : 0.0;
                }
            } else {
                // No assignment found: allow provided value or zero
                $calculatedHours = isset($data['pay_period_hours']) ? (float)$data['pay_period_hours'] : 0.0;
            }

            $data['pay_period_hours'] = $calculatedHours;
        } catch (\Throwable $e) {
            // On any error, default to provided or zero to avoid blocking request
            $data['pay_period_hours'] = isset($data['pay_period_hours']) ? (float)$data['pay_period_hours'] : 0.0;
        }

        $leave = MinimalLeaveRequest::create($data);

        // If the leave is approved, update the employee's leave balance on assignment
        try {
            $isApproved = (bool)($request->input('approved', false));
            if ($isApproved && isset($leaveTypeDropdown)) {
                $assignmentForBalance = EmpAssignLeaveType::where('employee_id', $data['employee_id'])
                    ->when($companyId, function ($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    })
                    ->where('leave_type_id', $leaveTypeDropdown->id)
                    ->first();

                if ($assignmentForBalance) {
                    $hoursToDeduct = (float)($data['pay_period_hours'] ?? 0);
                    if ($hoursToDeduct > 0) {
                        // Prefer deducting from opening balance if available; otherwise from annual accrued
                        if (!is_null($assignmentForBalance->openning_balance)) {
                            $newOpening = max(0, (float)$assignmentForBalance->openning_balance - $hoursToDeduct);
                            $assignmentForBalance->openning_balance = $newOpening;
                        } else {
                            $newAnnual = max(0, (float)($assignmentForBalance->hours_accured_annually ?? 0) - $hoursToDeduct);
                            $assignmentForBalance->hours_accured_annually = $newAnnual;
                        }

                        // Track updater if available
                        if ($user) {
                            $assignmentForBalance->updated_by = $user->id;
                        }
                        $assignmentForBalance->save();
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore balance update issues to not block leave creation
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave request created',
            'data' => $leave,
        ], 201);
    }

    /**
     * List leave requests visible to the current approver
     * Only users whose EmploymentInformation has authorised_to_approve_leave = true
     * and who belong to the same company (by token) can view company leave requests.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $companyId = $user->getCompanyIdFromToken();
        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID not found in token',
            ], 400);
        }

        // Try to map the user to an employee via email within the same company
        $employee = Employee::where('emp_company_id', $companyId)
            ->where('emp_email_address', $user->email)
            ->first();

        // Company owner fallback (owner/admins can view regardless of linkage)
        $isCompanyOwner = Company::where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->exists();

        if ($employee) {
            // Check approver authorization for the linked employee
            $employmentInfo = EmploymentInformation::where('employee_id', $employee->emp_id)
                ->where('authorised_to_approve_leave', true)
                ->first();

            if (!$employmentInfo && !$isCompanyOwner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not authorized to approve/view leave requests',
                ], 403);
            }
        } else if (!$isCompanyOwner) {
            // Neither linked employee nor company owner
            return response()->json([
                'success' => false,
                'message' => 'You are not linked to an employee profile in this company',
            ], 403);
        }

        // Fetch leave requests for all employees in this company
        $employeeIds = Employee::where('emp_company_id', $companyId)->pluck('emp_id');

        $requests = MinimalLeaveRequest::whereIn('employee_id', $employeeIds)
            ->orderByDesc('id')
            ->paginate($request->get('per_page', 25));

        // Build employee name map
        $empMap = Employee::whereIn('emp_id', $requests->pluck('employee_id')->unique())
            ->get()
            ->mapWithKeys(function ($e) {
                $name = trim(($e->emp_first_name ?? '').' '.($e->emp_last_name ?? ''));
                return [$e->emp_id => ($name !== '' ? $name : 'Unknown')];
            });

        // Transform to minimal payload
        $transformed = $requests->getCollection()->map(function ($r) use ($empMap) {
            $dropdown = Dropdown::where('type', 'leave_type')
                ->where('name', $r->leave_type)
                ->first();
            $leaveTypeId = $dropdown?->id;
            return [
                'leave_request_id' => $r->id,
                'employee_name' => $empMap[$r->employee_id] ?? 'Unknown',
                'leave_type_id' => $leaveTypeId,
                'leave_type' => $r->leave_type,
                'status' => $r->status ?? 'Pending',
                'from_date' => optional($r->start_date)->toDateString(),
                'end_date' => optional($r->end_date)->toDateString(),
                'description' => $r->description,
            ];
        });

        // Keep pagination meta but replace data
        $paginated = [
            'current_page' => $requests->currentPage(),
            'per_page' => $requests->perPage(),
            'total' => $requests->total(),
            'last_page' => $requests->lastPage(),
            'data' => $transformed->values(),
        ];

        return response()->json([
            'success' => true,
            'data' => $paginated,
        ]);
    }

    /**
     * Approve a leave request (authorized users or company owner only)
     */
    public function approveLeave($leaveRequestId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
        }

        $companyId = $user->getCompanyIdFromToken();
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => 'Company ID not found in token'], 400);
        }

        $leave = MinimalLeaveRequest::find($leaveRequestId);
        if (!$leave) {
            return response()->json(['success' => false, 'message' => 'Leave request not found'], 404);
        }

        $employee = Employee::where('emp_id', $leave->employee_id)
            ->where('emp_company_id', $companyId)
            ->first();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not in your company'], 403);
        }

        // Check authorization: approver or company owner
        $isOwner = Company::where('company_id', $companyId)->where('user_id', $user->id)->exists();
        $isApprover = EmploymentInformation::where('employee_id', $employee->emp_id)
            ->where('authorised_to_approve_leave', true)->exists();
        if (!$isOwner && !$isApprover) {
            return response()->json(['success' => false, 'message' => 'Not authorized to approve leave'], 403);
        }

        if ($leave->status === 'Approved') {
            return response()->json(['success' => true, 'message' => 'Already approved']);
        }

        // Deduct balance using same logic as create (uses pay_period_hours already saved)
        try {
            $leaveTypeDropdown = Dropdown::where('type', 'leave_type')
                ->where('name', $leave->leave_type)->first();
            if ($leaveTypeDropdown) {
                $assignment = EmpAssignLeaveType::where('employee_id', $leave->employee_id)
                    ->where('company_id', $companyId)
                    ->where('leave_type_id', $leaveTypeDropdown->id)
                    ->first();
                if ($assignment) {
                    $hoursToDeduct = (float)($leave->pay_period_hours ?? 0);
                    if ($hoursToDeduct > 0) {
                        if (!is_null($assignment->openning_balance)) {
                            $assignment->openning_balance = max(0, (float)$assignment->openning_balance - $hoursToDeduct);
                        } else {
                            $assignment->hours_accured_annually = max(0, (float)($assignment->hours_accured_annually ?? 0) - $hoursToDeduct);
                        }
                        $assignment->updated_by = $user->id;
                        $assignment->save();
                    }
                }
            }
        } catch (\Throwable $e) {}

        $leave->status = 'Approved';
        $leave->approved_by = $user->id;
        $leave->approved_at = now();
        $leave->reject_reason = null;
        $leave->save();

        return response()->json(['success' => true, 'message' => 'Leave approved']);
    }

    /**
     * Reject a leave request (requires reject_reason). Authorized users or owner only.
     */
    public function rejectLeave(Request $request, $leaveRequestId)
    {
        $request->validate(['reject_reason' => 'required|string|max:1000']);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
        }

        $companyId = $user->getCompanyIdFromToken();
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => 'Company ID not found in token'], 400);
        }

        $leave = MinimalLeaveRequest::find($leaveRequestId);
        if (!$leave) {
            return response()->json(['success' => false, 'message' => 'Leave request not found'], 404);
        }

        $employee = Employee::where('emp_id', $leave->employee_id)
            ->where('emp_company_id', $companyId)
            ->first();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not in your company'], 403);
        }

        $isOwner = Company::where('company_id', $companyId)->where('user_id', $user->id)->exists();
        $isApprover = EmploymentInformation::where('employee_id', $employee->emp_id)
            ->where('authorised_to_approve_leave', true)->exists();
        if (!$isOwner && !$isApprover) {
            return response()->json(['success' => false, 'message' => 'Not authorized to reject leave'], 403);
        }

        $leave->status = 'Rejected';
        $leave->reject_reason = $request->reject_reason;
        $leave->approved_by = $user->id;
        $leave->approved_at = now();
        $leave->save();

        return response()->json(['success' => true, 'message' => 'Leave rejected']);
    }

    /**
     * Show a single leave request by ID (approvers or company owner)
     */
    public function showLeave($leaveRequestId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
        }

        $companyId = $user->getCompanyIdFromToken();
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => 'Company ID not found in token'], 400);
        }

        $leave = MinimalLeaveRequest::find($leaveRequestId);
        if (!$leave) {
            return response()->json(['success' => false, 'message' => 'Leave request not found'], 404);
        }

        $employee = Employee::where('emp_id', $leave->employee_id)
            ->where('emp_company_id', $companyId)
            ->first();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not in your company'], 403);
        }

        $isOwner = Company::where('company_id', $companyId)->where('user_id', $user->id)->exists();
        $isApprover = EmploymentInformation::where('employee_id', $employee->emp_id)
            ->where('authorised_to_approve_leave', true)->exists();
        if (!$isOwner && !$isApprover) {
            return response()->json(['success' => false, 'message' => 'Not authorized to view leave'], 403);
        }

        $employeeName = trim(($employee->emp_first_name ?? '').' '.($employee->emp_last_name ?? ''));
        $dropdown = Dropdown::where('type', 'leave_type')
            ->where('name', $leave->leave_type)
            ->first();
        $leaveTypeId = $dropdown?->id;

        return response()->json([
            'success' => true,
            'data' => [
                'employee_name' => $employeeName !== '' ? $employeeName : 'Unknown',
                'leave_type_id' => $leaveTypeId,
                'leave_type' => $leave->leave_type,
                'status' => $leave->status ?? 'Pending',
                'from_date' => optional($leave->start_date)->toDateString(),
                'end_date' => optional($leave->end_date)->toDateString(),
                'description' => $leave->description,
                'leave_request_id' => $leave->id,
            ],
        ]);
    }

    /**
     * Store a new assigned leave type for an employee
     */
    public function assignLeaveType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,emp_id',
            'leave_type_id' => 'required|integer',
            'leave_cat' => 'required|string|max:100',
            'leave_calculation_method_id' => 'required|integer',
            'hours_accured_annually' => 'required|numeric|min:0',
            'openning_balance' => 'nullable|numeric|min:0',
            'hours_employee_works_fortnightly' => 'nullable|numeric|min:0',
            'termination_unused_balnce' => 'required|in:Not Paid Out,Paid_out',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get the authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // Get company_id from token
        $companyId = $user->getCompanyIdFromToken();
        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID not found in token',
            ], 400);
        }

        // Verify the employee belongs to the same company
        $employee = Employee::where('emp_id', $request->employee_id)
            ->where('emp_company_id', $companyId)
            ->first();
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found or does not belong to your company',
            ], 404);
        }

        // Check if employee already has this leave_type_id assigned
        $existingAssignment = EmpAssignLeaveType::where('employee_id', $request->employee_id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('company_id', $companyId)
            ->first();
        
        if ($existingAssignment) {
            return response()->json([
                'success' => false,
                'message' => 'Employee already has this leave type assigned',
                'data' => [
                    'existing_assignment_id' => $existingAssignment->id,
                    'leave_type_id' => $request->leave_type_id,
                ],
            ], 409); // 409 Conflict
        }

        // Add created_by and company_id to the validated data
        $data = $validator->validated();
        $data['created_by'] = $user->id;
        $data['company_id'] = $companyId;

        $assignLeaveType = EmpAssignLeaveType::create($data);

        // Hide internal fields
        $assignLeaveType->makeHidden(['created_by']);

        return response()->json([
            'success' => true,
            'message' => 'Leave type assigned successfully',
            'data' => $assignLeaveType,
        ], 201);
    }

    /**
     * Display a single assigned leave type
     */
    public function showAssignLeaveType($id)
    {
        // Get the authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // Get company_id from token
        $companyId = $user->getCompanyIdFromToken();
        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID not found in token',
            ], 400);
        }

        $assignLeaveType = EmpAssignLeaveType::where('id', $id)
          ->where('company_id', $companyId)
          ->first();

        if (!$assignLeaveType) {
            return response()->json([
                'success' => false,
                'message' => 'Assigned leave type not found or does not belong to your company',
            ], 404);
        }

        // Hide internal fields
        if ($assignLeaveType) {
            $assignLeaveType->makeHidden(['created_by']);
        }

        return response()->json([
            'success' => true,
            'data' => $assignLeaveType,
        ], 200);
    }

    /**
     * Get all assigned leave types for an employee
     */
    public function getEmployeeAssignLeaveTypes($employeeId)
    {
        // Get the authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // Get company_id from token
        $companyId = $user->getCompanyIdFromToken();
        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID not found in token',
            ], 400);
        }

        $employee = Employee::where('emp_id', $employeeId)
            ->where('emp_company_id', $companyId)
            ->first();
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found or does not belong to your company',
            ], 404);
        }

        $assignLeaveTypes = EmpAssignLeaveType::where('employee_id', $employeeId)
          ->where('company_id', $companyId)
          ->get();

        // Hide internal fields on each record
        $assignLeaveTypes->makeHidden(['created_by']);

        return response()->json([
            'success' => true,
            'data' => $assignLeaveTypes,
        ], 200);
    }

    /**
     * Update an assigned leave type
     */
    public function updateAssignLeaveType(Request $request, $id)
    {
        // Get the authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // Get company_id from token
        $companyId = $user->getCompanyIdFromToken();
        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID not found in token',
            ], 400);
        }

        $assignLeaveType = EmpAssignLeaveType::where('id', $id)
            ->where('company_id', $companyId)
            ->first();
            
        if (!$assignLeaveType) {
            return response()->json([
                'success' => false,
                'message' => 'Assigned leave type not found or does not belong to your company',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'leave_type_id' => 'sometimes|integer',
            'leave_cat' => 'sometimes|string|max:100',
            'leave_calculation_method_id' => 'sometimes|integer',
            'hours_accured_annually' => 'sometimes|numeric|min:0',
            'openning_balance' => 'nullable|numeric|min:0',
            'hours_employee_works_fortnightly' => 'nullable|numeric|min:0',
            'termination_unused_balnce' => 'sometimes|in:Not Paid Out,Paid_out',
        ]);

        // If leave_type_id is being updated, check for duplicates
        if ($request->has('leave_type_id') && $request->leave_type_id != $assignLeaveType->leave_type_id) {
            $existingAssignment = EmpAssignLeaveType::where('employee_id', $assignLeaveType->employee_id)
                ->where('leave_type_id', $request->leave_type_id)
                ->where('company_id', $companyId)
                ->where('id', '!=', $id) // Exclude current record
                ->first();
            
            if ($existingAssignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee already has this leave type assigned',
                    'data' => [
                        'existing_assignment_id' => $existingAssignment->id,
                        'leave_type_id' => $request->leave_type_id,
                    ],
                ], 409); // 409 Conflict
            }
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get the authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // Add updated_by to the validated data
        $data = $validator->validated();
        $data['updated_by'] = $user->id;

        $assignLeaveType->update($data);

        // Hide internal fields
        $assignLeaveType->makeHidden(['created_by']);

        return response()->json([
            'success' => true,
            'message' => 'Assigned leave type updated successfully',
            'data' => $assignLeaveType,
        ], 200);
    }

    /**
     * Delete an assigned leave type
     */
    public function deleteAssignLeaveType($id)
    {
        // Get the authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // Get company_id from token
        $companyId = $user->getCompanyIdFromToken();
        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID not found in token',
            ], 400);
        }

        $assignLeaveType = EmpAssignLeaveType::where('id', $id)
            ->where('company_id', $companyId)
            ->first();
            
        if (!$assignLeaveType) {
            return response()->json([
                'success' => false,
                'message' => 'Assigned leave type not found or does not belong to your company',
            ], 404);
        }

        $assignLeaveType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Assigned leave type deleted successfully',
        ], 200);
    }

    /**
     * Get dropdown options for leave types
     */
    public function getLeaveTypeDropdowns()
    {
        $leaveTypes = Dropdown::where('type', 'leave_type')
            ->whereNull('parent_id')
            ->with('children')
            ->get();

        $calculationMethods = Dropdown::where('type', 'leave_calculation_method')
            ->get();

        $terminationOptions = Dropdown::where('type', 'termination_option')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'leave_types' => $leaveTypes,
                'calculation_methods' => $calculationMethods,
                'termination_options' => $terminationOptions,
            ],
        ], 200);
    }

    /**
     * Get available leave types for assignment (excluding already assigned ones)
     */
    public function getAvailableLeaveTypesForEmployee($employeeId)
    {
        // Get the authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // Get company_id from token
        $companyId = $user->getCompanyIdFromToken();
        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID not found in token',
            ], 400);
        }

        // Verify employee belongs to the company
        $employee = Employee::where('emp_id', $employeeId)
            ->where('emp_company_id', $companyId)
            ->first();
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found or does not belong to your company',
            ], 404);
        }

        // Get already assigned leave type IDs for this employee
        $assignedLeaveTypeIds = EmpAssignLeaveType::where('employee_id', $employeeId)
            ->where('company_id', $companyId)
            ->pluck('leave_type_id')
            ->toArray();

        // Get all available leave types (excluding already assigned ones)
        $availableLeaveTypes = Dropdown::where('type', 'leave_type')
            ->whereNull('parent_id')
            ->whereNotIn('id', $assignedLeaveTypeIds)
            ->pluck('name')
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'leave_types' => $availableLeaveTypes,
            ],
        ], 200);
    }

    /**
     * Get leave balances for an employee
     * Returns: type (leave name), balance_hours (current), opening_hours (initial)
     */
    public function getEmployeeLeaveBalances($employeeId)
    {
        // Auth
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // Company from token
        $companyId = $user->getCompanyIdFromToken();
        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID not found in token',
            ], 400);
        }

        // Validate employee belongs to company
        $employee = Employee::where('emp_id', $employeeId)
            ->where('emp_company_id', $companyId)
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found or does not belong to your company',
            ], 404);
        }

        // Fetch assignments
        $assignments = EmpAssignLeaveType::where('employee_id', $employeeId)
            ->where('company_id', $companyId)
            ->get();

        // Map to balances response
        $balances = $assignments->map(function ($a) {
            $leaveDropdown = Dropdown::find($a->leave_type_id);
            $leaveName = $leaveDropdown?->name ?? 'Unknown';

            $opening = (float)($a->openning_balance ?? 0);
            $annual = (float)($a->hours_accured_annually ?? 0);
            // Current balance: prefer tracked opening balance when present; otherwise annual accrual
            $current = $a->openning_balance !== null ? $opening : $annual;

            return [
                'type' => $leaveName,
                'balance_hours' => round($current, 4),
                'opening_hours' => round($opening, 4),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $balances,
        ], 200);
    }

    /**
     * Compute pay_period_hours for an employee and a leave_type_id
     * GET /employees/{employeeId}/leave-types/{leaveTypeId}/pay-period-hours
     */
    public function getPayPeriodHours($employeeId, $leaveTypeId)
    {
        // Auth
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $companyId = $user->getCompanyIdFromToken();
        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID not found in token',
            ], 400);
        }

        // Validate employee belongs to company
        $employee = Employee::where('emp_id', $employeeId)
            ->where('emp_company_id', $companyId)
            ->first();
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found or does not belong to your company',
            ], 404);
        }

        // Validate leave type exists
        $leaveType = Dropdown::where('type', 'leave_type')->where('id', $leaveTypeId)->first();
        if (!$leaveType) {
            return response()->json([
                'success' => false,
                'message' => 'Leave type not found',
            ], 404);
        }

        // Find assignment
        $assignment = EmpAssignLeaveType::where('employee_id', $employeeId)
            ->where('company_id', $companyId)
            ->where('leave_type_id', $leaveTypeId)
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => true,
                'data' => [
                    'leave_type_id' => (int)$leaveTypeId,
                    'leave_type' => $leaveType->name,
                    'balance' => 0.0,
                    'calculation_method' => null,
                ],
            ], 200);
        }

        $methodName = optional(Dropdown::find($assignment->leave_calculation_method_id))->name;
        $calculatedHours = 0.0;
        switch ($methodName) {
            case 'Fixed Amount Each Period':
                $calculatedHours = (float)($assignment->openning_balance ?? 0);
                break;
            case 'Manually Recorded Rate':
                $calculatedHours = 0.0; // client should provide when creating
                break;
            case 'No Calculation Required':
                $calculatedHours = 0.0;
                break;
            case 'Based on Ordinary Earnings':
                if (!is_null($assignment->hours_employee_works_fortnightly)) {
                    $calculatedHours = (float)$assignment->hours_employee_works_fortnightly;
                } else {
                    $calculatedHours = round(((float)($assignment->hours_accured_annually ?? 0)) / 26, 2);
                }
                break;
            default:
                $calculatedHours = 0.0;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'leave_type_id' => (int)$leaveTypeId,
                'leave_type' => $leaveType->name,
                'balance' => $calculatedHours,
                'calculation_method' => $methodName,
            ],
        ], 200);
    }
}


