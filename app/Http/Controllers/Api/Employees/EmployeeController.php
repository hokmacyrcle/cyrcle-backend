<?php

namespace App\Http\Controllers\Api\Employees;
use App\Http\Requests\EmployeeRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use App\Models\EmploymentInformation;
class EmployeeController extends Controller
{
       /**
     * Display a listing of employees with optional search and status filter.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            
            // Apply company filter based on authenticated user's company context
            if ($user && isset($user->company_id)) {
                $query = Employee::where('emp_company_id', $user->company_id);
            } else {
                // If no company context, return empty result for security
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            // Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('emp_first_name', 'like', "%{$search}%")
                      ->orWhere('emp_middle_name', 'like', "%{$search}%")
                      ->orWhere('emp_last_name', 'like', "%{$search}%")
                      ->orWhere('emp_email_address', 'like', "%{$search}%")
                      ->orWhere('emp_phone_number', 'like', "%{$search}%")
                      ->orWhere('emp_mobile_number', 'like', "%{$search}%")
                      ->orWhere('emp_tfn', 'like', "%{$search}%");
                });
            }

            // Status filter
            if ($request->filled('status') && in_array($request->status, ['Active','Resigned','Terminated','Inactive'])) {
                $query->where('emp_status', $request->status);
            }

            $perPage = $request->get('per_page', 15);
            $employees = $query->with(['employmentInformation'])->orderBy('created_at', 'desc')->paginate($perPage);

            // Transform collection to required response shape
            $employees->setCollection(
                $employees->getCollection()->transform(function ($employee) {
                    $info = $employee->employmentInformation;
                    $calendar = $info?->pay_frequency ?? null;
                    $employeeGroup = $info?->employee_group ?? null;
                    $nextPaymentDate = $this->computeNextPaymentDate($info);

                    return [
                        'first_name' => $employee->emp_first_name,
                        'last_name' => $employee->emp_last_name,
                        'calendar' => $calendar,
                        'employee_group' => $employeeGroup,
                        'next_payment_date' => $nextPaymentDate,
                        'employee_id' => $employee->emp_id,
                    ];
                })
            );

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Employees retrieved successfully',
                'data' => $employees
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to retrieve employees',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Compute next payment date from employment information.
     */
    private function computeNextPaymentDate($employmentInformation): ?string
    {
        if (!$employmentInformation) {
            return null;
        }

        $startDate = $employmentInformation->start_date ? Carbon::parse($employmentInformation->start_date) : null;
        if (!$startDate) {
            return null;
        }

        $frequency = $employmentInformation->pay_frequency ?? null;
        $next = Carbon::now();

        // Normalize and compute based on rough rules
        switch (strtolower((string) $frequency)) {
            case 'weekly':
                $intervalDays = 7;
                break;
            case 'fortnightly calendar':
            case 'fortnightly':
                $intervalDays = 14;
                break;
            case 'monthly':
                // Move to the same day next month
                $candidate = Carbon::now()->startOfDay();
                // Ensure first cycle from start date
                while ($candidate->lt($startDate)) {
                    $candidate->addMonth();
                }
                while ($candidate->lte(Carbon::now())) {
                    $candidate->addMonth();
                }
                return $candidate->format('d M Y');
            default:
                $intervalDays = null;
        }

        if ($intervalDays) {
            $candidate = $startDate->copy();
            while ($candidate->lte(Carbon::now())) {
                $candidate->addDays($intervalDays);
            }
            return $candidate->format('d M Y');
        }

        return null;
    }

    /**
     * Map dropdown integer IDs to their corresponding string values
     * Uses exact same arrays as employeeDropdown method
     */
    private function mapDropdownIdsToStrings(array $data): array
    {
        // Use exact same arrays as employeeDropdown method
        $incomeTypes = [
            'Salary and wages',
            'Commission',
            'Bonus',
            'Overtime',
            'Allowance',
        ];

        $employmentBasis = [
            'Full-time',
            'Part-time',
            'Casual',
            'Fixed-term',
            'Seasonal',
        ];

        $payFrequencies = [
            'Weekly',
            'Fortnightly Calendar',
            'Monthly',
            'Quarterly',
            'Annually',
        ];

        // Predefined employee groups
        $predefinedEmployeeGroups = [
            'IT / Engineering',
            'Sales',
            'Marketing',
            'Finance & Accounts',
            'Human Resources (HR)',
            'Operations / Administration',
            'Customer Support / Helpdesk',
            'Product / R&D',
            'Business Development',
            'Procurement / Supply Chain',
            'Quality Assurance (QA)',
            'Legal / Compliance',
            'Management / Leadership group'
        ];

        // Get distinct employee groups from database and merge with predefined ones
        $dbEmployeeGroups = EmploymentInformation::query()
            ->whereNotNull('employee_group')
            ->distinct()
            ->orderBy('employee_group')
            ->pluck('employee_group')
            ->values()
            ->toArray();

        $employeeGroups = array_unique(array_merge($predefinedEmployeeGroups, $dbEmployeeGroups));

        $holidayGroups = EmploymentInformation::query()
            ->whereNotNull('holiday_group')
            ->distinct()
            ->orderBy('holiday_group')
            ->pluck('holiday_group')
            ->values()
            ->toArray();

        // Ordinary earnings rate values
        $ordinaryEarningsRates = [
            'Ordinary Time',
            'Overtime Rate',
            'Casual Ordinary Time',
        ];

        // Map integer IDs to string values
        $mapped = $data;

        if (isset($data['income_type'])) {
            $mapped['income_type'] = $incomeTypes[$data['income_type'] - 1] ?? null;
        }

        if (isset($data['employment_basis'])) {
            $mapped['employment_basis'] = $employmentBasis[$data['employment_basis'] - 1] ?? null;
        }

        if (isset($data['pay_frequency'])) {
            $mapped['pay_frequency'] = $payFrequencies[$data['pay_frequency'] - 1] ?? null;
        }

        if (isset($data['employee_group'])) {
            $mapped['employee_group'] = $employeeGroups[$data['employee_group'] - 1] ?? null;
        }

        if (isset($data['holiday_group'])) {
            $mapped['holiday_group'] = $holidayGroups[$data['holiday_group'] - 1] ?? null;
        }

        if (isset($data['ordinary_earnings_rate'])) {
            $mapped['ordinary_earnings_rate'] = $ordinaryEarningsRates[$data['ordinary_earnings_rate'] - 1] ?? null;
        }

        return $mapped;
    }

    /**
     * Map dropdown string values to their corresponding integer IDs
     * Uses exact same arrays as employeeDropdown method
     */
    private function mapDropdownStringsToIds(array $data): array
    {
        // Use exact same arrays as employeeDropdown method
        $incomeTypes = [
            'Salary and wages',
            'Commission',
            'Bonus',
            'Overtime',
            'Allowance',
        ];

        $employmentBasis = [
            'Full-time',
            'Part-time',
            'Casual',
            'Fixed-term',
            'Seasonal',
        ];

        $payFrequencies = [
            'Weekly',
            'Fortnightly Calendar',
            'Monthly',
            'Quarterly',
            'Annually',
        ];

        // Predefined employee groups
        $predefinedEmployeeGroups = [
            'IT / Engineering',
            'Sales',
            'Marketing',
            'Finance & Accounts',
            'Human Resources (HR)',
            'Operations / Administration',
            'Customer Support / Helpdesk',
            'Product / R&D',
            'Business Development',
            'Procurement / Supply Chain',
            'Quality Assurance (QA)',
            'Legal / Compliance',
            'Management / Leadership group'
        ];

        // Get distinct employee groups from database and merge with predefined ones
        $dbEmployeeGroups = EmploymentInformation::query()
            ->whereNotNull('employee_group')
            ->distinct()
            ->orderBy('employee_group')
            ->pluck('employee_group')
            ->values()
            ->toArray();

        $employeeGroups = array_unique(array_merge($predefinedEmployeeGroups, $dbEmployeeGroups));

        $holidayGroups = EmploymentInformation::query()
            ->whereNotNull('holiday_group')
            ->distinct()
            ->orderBy('holiday_group')
            ->pluck('holiday_group')
            ->values()
            ->toArray();

        // Ordinary earnings rate values
        $ordinaryEarningsRates = [
            'Ordinary Time',
            'Overtime Rate',
            'Casual Ordinary Time',
        ];

        // Map string values to integer IDs
        $mapped = $data;

        if (isset($data['income_type'])) {
            $index = array_search($data['income_type'], $incomeTypes);
            $mapped['income_type'] = $index !== false ? $index + 1 : null;
        }

        if (isset($data['employment_basis'])) {
            $index = array_search($data['employment_basis'], $employmentBasis);
            $mapped['employment_basis'] = $index !== false ? $index + 1 : null;
        }

        if (isset($data['pay_frequency'])) {
            $index = array_search($data['pay_frequency'], $payFrequencies);
            $mapped['pay_frequency'] = $index !== false ? $index + 1 : null;
        }

        if (isset($data['employee_group'])) {
            $index = array_search($data['employee_group'], $employeeGroups);
            $mapped['employee_group'] = $index !== false ? $index + 1 : null;
        }

        if (isset($data['holiday_group'])) {
            $index = array_search($data['holiday_group'], $holidayGroups);
            $mapped['holiday_group'] = $index !== false ? $index + 1 : null;
        }

        if (isset($data['ordinary_earnings_rate'])) {
            $index = array_search($data['ordinary_earnings_rate'], $ordinaryEarningsRates);
            $mapped['ordinary_earnings_rate'] = $index !== false ? $index + 1 : null;
        }

        return $mapped;
    }

    /**
     * Create or update employment information for an employee.
     */
    public function updateEmployeeContractInformation(Request $request): JsonResponse
    {
        $validated = $request->validate([

            'employee_id' => 'required|exists:employees,emp_id',
            'employment_type' => 'required|in:Employee,Contractor',
            'income_type' => 'required|integer',
            'employment_basis' => 'required|integer',
            'start_date' => 'required|date',
            'classification' => 'nullable|string|max:255',
            'pay_frequency' => 'required|integer',
            'employee_group' => 'nullable|integer',
            'holiday_group' => 'nullable|integer',
            'include_holidays_in_payslips' => 'boolean',
            'ordinary_earnings_rate' => 'required|integer',
            'authorised_to_approve_leave' => 'boolean',
            'authorised_to_approve_timesheets' => 'boolean',
            'authorised_fields' => 'nullable|string|max:2000',
            'superannuation_memberships' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
            
        ]);

        try {
            // Ensure employee belongs to current company
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            $employee = Employee::where('emp_company_id', $user->company_id ?? null)
                ->findOrFail($validated['employee_id']);

            // Map integer IDs to string values
            $mappedData = $this->mapDropdownIdsToStrings($validated);

            $employmentInfo = EmploymentInformation::updateOrCreate(
                ['employee_id' => $validated['employee_id']],
                $mappedData
            );

            $employmentInfo->refresh();
            $responseData = Arr::only($employmentInfo->toArray(), array_keys($validated));
            $responseData = $this->mapDropdownStringsToIds($responseData);
            $responseData['employment_info_id'] = $employmentInfo->employment_info_id;

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Employment information saved successfully',
                'data' => $responseData,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Employee not found or access denied'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to save employment information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get employment information for an employee by employee_id.
     * Returns same shape as updateEmployeeInformation (filtered fields + employment_info_id).
     */
    public function getEmployeeContractInformation(string $employee_id): JsonResponse
    {
        try {
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            $employee = Employee::where('emp_company_id', $user->company_id ?? null)
                ->findOrFail($employee_id);

            $employmentInfo = EmploymentInformation::where('employee_id', $employee_id)->first();
            if (!$employmentInfo) {
                return response()->json([
                    'status' => false,
                    'status_code' => 404,
                    'message' => 'Employment information not found for this employee'
                ], 404);
            }

            $payloadKeys = [
                'employee_id',
                'employment_type',
                'income_type',
                'employment_basis',
                'start_date',
                'classification',
                'pay_frequency',
                'employee_group',
                'holiday_group',
                'include_holidays_in_payslips',
                'ordinary_earnings_rate',
                'authorised_to_approve_leave',
                'authorised_to_approve_timesheets',
                'authorised_fields',
                'superannuation_memberships',
                'notes',
            ];

            $data = Arr::only($employmentInfo->toArray(), $payloadKeys);
            $data = $this->mapDropdownStringsToIds($data);
            $data['employment_info_id'] = $employmentInfo->employment_info_id;

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Employment information retrieved successfully',
                'data' => $data,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Employee not found or access denied'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to fetch employment information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Store a newly created employee.
     */
    public function store(EmployeeRequest $request): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'User must be associated with a company to create employees'
                ], 403);
            }

            // Add company_id to the validated data
            $data = $request->validated();
            $data['emp_company_id'] = $user->company_id;
            
            $employee = Employee::create($data);
            $auditLog = $employee->latestAuditLog ?? null;

            return response()->json([
                'status' => true,
                'status_code' => 201,
                'message' => 'Employee created successfully',
                'data' => $employee,
                'audit_log' => $auditLog
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to create employee',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Display a specific employee.
     */
    public function show(string $id): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'User must be associated with a company to access employees'
                ], 403);
            }

            $employee = Employee::where('emp_company_id', $user->company_id)->findOrFail($id);

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Employee retrieved successfully',
                'data' => $employee
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Employee not found'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to retrieve employee',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update an employee.
     */
    public function update(EmployeeRequest $request, string $id): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'User must be associated with a company to update employees'
                ], 403);
            }

            $employee = Employee::where('emp_company_id', $user->company_id)->findOrFail($id);
            $employee->update($request->validated());
            $auditLog = $employee->latestAuditLog ?? null;

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Employee updated successfully',
                'data' => $employee,
                'audit_log' => $auditLog
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Employee not found'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to update employee',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete an employee.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'User must be associated with a company to delete employees'
                ], 403);
            }

            $employee = Employee::where('emp_company_id', $user->company_id)->findOrFail($id);
            $auditLog = $employee->latestAuditLog ?? null;
            $employee->delete();

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Employee deleted successfully',
                'audit_log' => $auditLog
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Employee not found'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to delete employee',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get active employees only.
     */
    public function active(): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'User must be associated with a company to access employees'
                ], 403);
            }

            $employees = Employee::where('emp_company_id', $user->company_id)
                ->where('emp_status', 'Active')
                ->orderBy('emp_first_name')
                ->get();

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Active employees retrieved successfully',
                'data' => $employees
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to retrieve active employees',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Toggle employee status.
     */
    public function toggleStatus(string $id): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'User must be associated with a company to toggle employee status'
                ], 403);
            }

            $employee = Employee::where('emp_company_id', $user->company_id)->findOrFail($id);

            $employee->update([
                'emp_status' => $employee->emp_status === 'Active' ? 'Inactive' : 'Active'
            ]);

            $auditLog = $employee->latestAuditLog ?? null;

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Employee status updated successfully',
                'data' => [
                    'id' => $employee->emp_id,
                    'status' => $employee->emp_status
                ],
                'audit_log' => $auditLog
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Employee not found'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to update employee status',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get audit history for an employee.
     */
    public function auditHistory(string $id): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'User must be associated with a company to access employee audit history'
                ], 403);
            }

            $employee = Employee::where('emp_company_id', $user->company_id)->findOrFail($id);
            $auditLogs = $employee->auditLogs()
                ->with('performer')
                ->orderBy('performed_at', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Employee audit history retrieved successfully',
                'data' => [
                    'employee' => $employee,
                    'audit_logs' => $auditLogs
                ]
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Employee not found'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to retrieve employee audit history',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get employee details for form consumption (same fields as employee-form show).
     */
    public function employee_details(string $id): JsonResponse
    {
        try {
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'User must be associated with a company to access employees'
                ], 403);
            }

            $employee = Employee::with(['attachments'])
                ->where('emp_company_id', $user->company_id)
                ->findOrFail($id);

            $keys = [
                'emp_id','emp_title','emp_first_name','emp_middle_name','emp_last_name','emp_date_of_birth',
                'emp_job_title','emp_address_search','emp_xero_me_invitation','emp_emergency_contact_name',
                'emp_emergency_contact_relationship','emp_emergency_contact_phone','emp_emergency_contact_email',
                'emp_address_line_1','emp_address_line_2','emp_address_line_3','emp_city','emp_state','emp_post_code','emp_country',
                'profile_pic'
            ];

            $data = array_intersect_key($employee->toArray(), array_flip($keys));

            // Compute single profile image like employee-form
            $attachmentsArray = $employee->attachments ? $employee->attachments->toArray() : [];
            $imageMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
            $profileImage = null;
            foreach ($attachmentsArray as $att) {
                if (isset($att['mime_type']) && in_array($att['mime_type'], $imageMimes)) {
                    $profileImage = $att;
                    break;
                }
            }
            $data['profile_pic'] = $profileImage ?: null;

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Employee details retrieved successfully',
                'data' => $data
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Employee not found'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to retrieve employee details',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update employee basic details; accepts optional profile_image, updates profile_pic path
     */
    public function updateEmployeeDetails(Request $request, string $id): JsonResponse
    {
        try {
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'User must be associated with a company to update employees'
                ], 403);
            }

            $employee = Employee::where('emp_company_id', $user->company_id)->findOrFail($id);

            $validated = $request->validate([
                'emp_title' => 'nullable|string|max:10',
                'emp_first_name' => 'nullable|string|max:255',
                'emp_middle_name' => 'nullable|string|max:255',
                'emp_last_name' => 'nullable|string|max:255',
                'emp_date_of_birth' => 'nullable|date|before:today',
                'emp_job_title' => 'nullable|string|max:255',
                'emp_address_search' => 'nullable|string|max:500',
                'emp_xero_me_invitation' => 'boolean',
                'emp_emergency_contact_name' => 'nullable|string|max:255',
                'emp_emergency_contact_relationship' => 'nullable|string|max:255',
                'emp_emergency_contact_phone' => 'nullable|string|max:20',
                'emp_emergency_contact_email' => 'nullable|email|max:255',
                'emp_address_line_1' => 'nullable|string|max:255',
                'emp_address_line_2' => 'nullable|string|max:255',
                'emp_address_line_3' => 'nullable|string|max:255',
                'emp_city' => 'nullable|string|max:255',
                'emp_state' => 'nullable|string|max:255',
                'emp_post_code' => 'nullable|string|max:20',
                'emp_country' => 'nullable|string|max:255',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:20480',
            ]);

            // Update basic fields
            $employee->update(Arr::except($validated, ['profile_image']));

            // Optional profile image upload
            if ($request->hasFile('profile_image')) {
                $attachmentService = new \App\Services\CommonAttachmentService();
                if (!$attachmentService->validateFile($request->file('profile_image'))) {
                    return response()->json([
                        'status' => false,
                        'status_code' => 422,
                        'message' => 'Invalid file type or size. Allowed: images up to 20MB',
                    ], 422);
                }

                $result = $attachmentService->storeAttachment('Employee', $user->company_id, $employee->emp_id, $request->file('profile_image'));
                if ($result) {
                    $employee->profile_pic = $result['path'] ?? null;
                    $employee->save();
                }
            }

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Employee details updated successfully',
                'data' => $employee->fresh()
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Employee not found'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to update employee details',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Employee dropdown values for employment/contract details.
     */
    public function employeeDropdown(): JsonResponse
    {
        try {
            $incomeTypes = [
                'Salary and wages',
                'Commission',
                'Bonus',
                'Overtime',
                'Allowance',
            ];

            $employmentBasis = [
                'Full-time',
                'Part-time',
                'Casual',
                'Fixed-term',
                'Seasonal',
            ];

            $payFrequencies = [
                'Weekly',
                'Fortnightly Calendar',
                'Monthly',
                'Quarterly',
                'Annually',
            ];

            // Predefined employee groups
            $predefinedEmployeeGroups = [
                'IT / Engineering',
                'Sales',
                'Marketing',
                'Finance & Accounts',
                'Human Resources (HR)',
                'Operations / Administration',
                'Customer Support / Helpdesk',
                'Product / R&D',
                'Business Development',
                'Procurement / Supply Chain',
                'Quality Assurance (QA)',
                'Legal / Compliance',
                'Management / Leadership group'
            ];

            // Get distinct employee groups from database and merge with predefined ones
            $dbEmployeeGroups = EmploymentInformation::query()
                ->whereNotNull('employee_group')
                ->distinct()
                ->orderBy('employee_group')
                ->pluck('employee_group')
                ->values()
                ->toArray();

            $employeeGroups = array_unique(array_merge($predefinedEmployeeGroups, $dbEmployeeGroups));

            $holidayGroups = EmploymentInformation::query()
                ->whereNotNull('holiday_group')
                ->distinct()
                ->orderBy('holiday_group')
                ->pluck('holiday_group')
                ->values();

            // Ordinary earnings rate values could come from a config or table; provide a sensible default list
            $ordinaryEarningsRates = [
                'Ordinary Time',
                'Overtime Rate',
                'Casual Ordinary Time',
            ];

            // Normalize to [{id, name}] shape with integer IDs
            $mapList = function ($list) {
                return collect($list)->map(function ($value, $index) {
                    $id = is_array($value) ? ($value['id'] ?? ($index + 1)) : ($index + 1);
                    $name = is_array($value) ? ($value['name'] ?? (string)$value) : (string)$value;
                    return ['id' => (int)$id, 'name' => $name];
                })->values();
            };

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Employee dropdowns retrieved successfully',
                'data' => [
                    'income_type' => $mapList($incomeTypes),
                    'employment_basis' => $mapList($employmentBasis),
                    'pay_frequency' => $mapList($payFrequencies),
                    'employee_group' => $mapList($employeeGroups),
                    'holiday_group' => $mapList($holidayGroups),
                    'ordinary_earnings_rate' => $mapList($ordinaryEarningsRates),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to retrieve employee dropdowns',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
