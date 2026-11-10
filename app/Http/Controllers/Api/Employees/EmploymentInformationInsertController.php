<?php

namespace App\Http\Controllers\Api\Employees;

use App\Http\Controllers\Controller;
use App\Models\EmploymentInformation;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmploymentInformationInsertController extends Controller
{
    /**
     * Insert single employment information
     */
    public function insertSingle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,emp_id',
            'employment_type' => 'required|in:Employee,Contractor',
            'income_type' => 'required|string|max:255',
            'employment_basis' => 'required|in:Full-time,Part-time,Casual,Fixed-term,Seasonal',
            'start_date' => 'required|date',
            'classification' => 'nullable|string|max:255',
            'pay_frequency' => 'required|string|max:255',
            'employee_group' => 'nullable|string|max:255',
            'holiday_group' => 'nullable|string|max:255',
            'include_holidays_in_payslips' => 'boolean',
            'ordinary_earnings_rate' => 'required|string|max:255',
            'authorised_to_approve_leave' => 'boolean',
            'authorised_to_approve_timesheets' => 'boolean',
            'authorised_fields' => 'nullable|string|max:2000', // Comma-separated or JSON format
            'superannuation_memberships' => 'nullable|array',
            'superannuation_memberships.*.fund_name' => 'required_with:superannuation_memberships|string|max:255',
            'superannuation_memberships.*.member_number' => 'required_with:superannuation_memberships|string|max:255',
            'superannuation_memberships.*.fund_code' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Check if employment info already exists for this employee
            $existingInfo = EmploymentInformation::where('employee_id', $request->employee_id)->first();

            if ($existingInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employment information already exists for this employee',
                    'data' => $existingInfo->load('employee'),
                ], 400);
            }

            // Validate employee belongs to user's company
            $employee = Employee::find($request->employee_id);
            if (Auth::user()->company_id && $employee->emp_company_id !== Auth::user()->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this employee',
                ], 403);
            }

            $employmentInfo = EmploymentInformation::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Employment information created successfully',
                'data' => $employmentInfo->load('employee'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employment information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Insert multiple employment information records
     */
    public function insertMultiple(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employment_records' => 'required|array|min:1',
            'employment_records.*.employee_id' => 'required|exists:employees,emp_id',
            'employment_records.*.employment_type' => 'required|in:Employee,Contractor',
            'employment_records.*.income_type' => 'required|string|max:255',
            'employment_records.*.employment_basis' => 'required|in:Full-time,Part-time,Casual,Fixed-term,Seasonal',
            'employment_records.*.start_date' => 'required|date',
            'employment_records.*.classification' => 'nullable|string|max:255',
            'employment_records.*.pay_frequency' => 'required|string|max:255',
            'employment_records.*.employee_group' => 'nullable|string|max:255',
            'employment_records.*.holiday_group' => 'nullable|string|max:255',
            'employment_records.*.include_holidays_in_payslips' => 'boolean',
            'employment_records.*.ordinary_earnings_rate' => 'required|string|max:255',
            'employment_records.*.authorised_to_approve_leave' => 'boolean',
            'employment_records.*.authorised_to_approve_timesheets' => 'boolean',
            'employment_records.*.authorised_fields' => 'nullable|string|max:2000', // Comma-separated or JSON format
            'employment_records.*.superannuation_memberships' => 'nullable|array',
            'employment_records.*.notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $insertedRecords = [];
            $errors = [];
            $skipped = [];

            foreach ($request->employment_records as $recordData) {
                try {
                    // Check if employment info already exists
                    $existingInfo = EmploymentInformation::where('employee_id', $recordData['employee_id'])->first();

                    if ($existingInfo) {
                        $skipped[] = "Employment information already exists for employee {$recordData['employee_id']}";
                        continue;
                    }

                    // Validate employee belongs to user's company
                    $employee = Employee::find($recordData['employee_id']);
                    if (Auth::user()->company_id && $employee->emp_company_id !== Auth::user()->company_id) {
                        $skipped[] = "Access denied to employee {$recordData['employee_id']}";
                        continue;
                    }

                    $employmentInfo = EmploymentInformation::create($recordData);
                    $insertedRecords[] = $employmentInfo->load('employee');

                } catch (\Exception $e) {
                    $errors[] = "Failed to create employment info for employee {$recordData['employee_id']}: " . $e->getMessage();
                }
            }

            if (empty($insertedRecords)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No employment information records were created',
                    'errors' => $errors,
                    'skipped' => $skipped,
                ], 500);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($insertedRecords) . ' employment information records created successfully',
                'data' => $insertedRecords,
                'errors' => $errors,
                'skipped' => $skipped,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to insert employment information records',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk insert employment information for multiple employees
     */
    public function bulkInsert(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,emp_id',
            'employment_type' => 'required|in:Employee,Contractor',
            'income_type' => 'required|string|max:255',
            'employment_basis' => 'required|in:Full-time,Part-time,Casual,Fixed-term,Seasonal',
            'start_date' => 'required|date',
            'classification' => 'nullable|string|max:255',
            'pay_frequency' => 'required|string|max:255',
            'employee_group' => 'nullable|string|max:255',
            'holiday_group' => 'nullable|string|max:255',
            'include_holidays_in_payslips' => 'boolean',
            'ordinary_earnings_rate' => 'required|string|max:255',
            'authorised_to_approve_leave' => 'boolean',
            'authorised_to_approve_timesheets' => 'boolean',
            'authorised_fields' => 'nullable|string|max:2000', // Comma-separated or JSON format
            'superannuation_memberships' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $employeeIds = $request->employee_ids;
            $baseData = $request->except(['employee_ids']);
            
            $insertedCount = 0;
            $errors = [];
            $skipped = [];

            foreach ($employeeIds as $employeeId) {
                try {
                    // Check if employment info already exists
                    $existingInfo = EmploymentInformation::where('employee_id', $employeeId)->first();

                    if ($existingInfo) {
                        $skipped[] = "Employment information already exists for employee {$employeeId}";
                        continue;
                    }

                    // Validate employee belongs to user's company
                    $employee = Employee::find($employeeId);
                    if (Auth::user()->company_id && $employee->emp_company_id !== Auth::user()->company_id) {
                        $skipped[] = "Access denied to employee {$employeeId}";
                        continue;
                    }

                    $recordData = array_merge(['employee_id' => $employeeId], $baseData);
                    EmploymentInformation::create($recordData);
                    $insertedCount++;

                } catch (\Exception $e) {
                    $errors[] = "Failed to create employment info for employee {$employeeId}: " . $e->getMessage();
                }
            }

            if ($insertedCount === 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No employment information records were created',
                    'errors' => $errors,
                    'skipped' => $skipped,
                ], 500);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$insertedCount} employment information records created successfully",
                'data' => [
                    'inserted_count' => $insertedCount,
                    'employee_count' => count($employeeIds),
                    'employment_type' => $request->employment_type,
                    'employment_basis' => $request->employment_basis,
                ],
                'errors' => $errors,
                'skipped' => $skipped,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk insert employment information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update superannuation memberships for an employee
     */
    public function updateSuperannuation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,emp_id',
            'action' => 'required|in:add,remove,update',
            'fund_name' => 'required_if:action,add,update|string|max:255',
            'member_number' => 'required|string|max:255',
            'fund_code' => 'nullable|string|max:100',
            'new_data' => 'nullable|array', // For update action
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $employmentInfo = EmploymentInformation::where('employee_id', $request->employee_id)->first();

            if (!$employmentInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employment information not found for this employee',
                ], 404);
            }

            // Validate employee belongs to user's company
            $employee = Employee::find($request->employee_id);
            if (Auth::user()->company_id && $employee->emp_company_id !== Auth::user()->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this employee',
                ], 403);
            }

            $action = $request->action;
            $memberNumber = $request->member_number;

            switch ($action) {
                case 'add':
                    $employmentInfo->addSuperannuationFund(
                        $request->fund_name,
                        $memberNumber,
                        $request->fund_code
                    );
                    $message = 'Superannuation fund added successfully';
                    break;

                case 'remove':
                    $employmentInfo->removeSuperannuationFund($memberNumber);
                    $message = 'Superannuation fund removed successfully';
                    break;

                case 'update':
                    $newData = $request->new_data ?? [];
                    $employmentInfo->updateSuperannuationFund($memberNumber, $newData);
                    $message = 'Superannuation fund updated successfully';
                    break;

                default:
                    throw new \Exception('Invalid action specified');
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $employmentInfo->fresh()->load('employee'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update superannuation memberships',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get employment information statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = EmploymentInformation::getEmploymentStatistics();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get employment statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update authorized fields for an employee
     */
    public function updateAuthorizedFields(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,emp_id',
            'action' => 'required|in:set,add,remove,clear',
            'fields' => 'required_if:action,set,add|string|max:2000',
            'field_name' => 'required_if:action,remove|string|max:255',
            'format' => 'nullable|in:json,comma', // json or comma-separated
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $employmentInfo = EmploymentInformation::where('employee_id', $request->employee_id)->first();

            if (!$employmentInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employment information not found for this employee',
                ], 404);
            }

            // Validate employee belongs to user's company
            $employee = Employee::find($request->employee_id);
            if (Auth::user()->company_id && $employee->emp_company_id !== Auth::user()->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this employee',
                ], 403);
            }

            $action = $request->action;
            $format = $request->format ?? 'json';

            switch ($action) {
                case 'set':
                    $fields = $format === 'json' ? json_decode($request->fields, true) : explode(',', $request->fields);
                    $employmentInfo->setAuthorizedFields($fields, $format);
                    $message = 'Authorized fields set successfully';
                    break;

                case 'add':
                    $fields = $format === 'json' ? json_decode($request->fields, true) : explode(',', $request->fields);
                    foreach ($fields as $field) {
                        $employmentInfo->addAuthorizedField(trim($field));
                    }
                    $message = 'Authorized fields added successfully';
                    break;

                case 'remove':
                    $employmentInfo->removeAuthorizedField($request->field_name);
                    $message = 'Authorized field removed successfully';
                    break;

                case 'clear':
                    $employmentInfo->clearAuthorizedFields();
                    $message = 'Authorized fields cleared successfully';
                    break;

                default:
                    throw new \Exception('Invalid action specified');
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'employee_id' => $request->employee_id,
                    'authorized_fields' => $employmentInfo->authorized_fields_array,
                    'authorized_fields_count' => $employmentInfo->authorized_fields_count,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update authorized fields',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get employees by group
     */
    public function getEmployeesByGroup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'group' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $employees = EmploymentInformation::getEmployeesByGroup($request->group);

            return response()->json([
                'success' => true,
                'data' => $employees,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get employees by group',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
