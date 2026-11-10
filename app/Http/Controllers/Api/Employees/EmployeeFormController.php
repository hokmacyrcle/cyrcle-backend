<?php

namespace App\Http\Controllers\Api\Employees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Company;
use App\Models\EmployeeDesignation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\CommonAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class EmployeeFormController extends Controller
{
    /**
     * Get form data for creating/editing employee
     */
    public function getFormData()
    {
        try {
            $data = [
                'companies' => Company::select('company_id', 'company_name')->get(),
                'designations' => EmployeeDesignation::select('id', 'designation_name')->get(),
                'titles' => ['Mr.', 'Ms.', 'Mrs.', 'Dr.', 'Prof.', 'Sir', 'Lady'],
                'genders' => ['Male', 'Female', 'Other'],
                'work_authorizations' => ['Visa', 'Permanent Resident', 'Citizen'],
                'employment_types' => ['Contract', 'Part Time', 'Full Time', 'Casual'],
                'emp_statuses' => ['Active', 'Resigned', 'Terminated', 'Inactive'],
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch form data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new employee
     */
    public function store(Request $request)
    {
        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                // Basic Information
                'emp_title' => 'nullable|string|max:10',
                'emp_first_name' => 'required|string|max:255',
                'emp_middle_name' => 'nullable|string|max:255',
                'emp_last_name' => 'required|string|max:255',
                'emp_date_of_birth' => 'required|date|before:today',
                'emp_job_title' => 'required|string|max:255',
                'emp_gender' => 'required|in:Male,Female,Other',
                
                // Contact Information
                'emp_email_address' => 'required|email|max:255',
                'emp_phone_number' => 'nullable|string|max:20',
                'emp_xero_me_invitation' => 'required|boolean',
                
                // Emergency Contact
                'emp_emergency_contact_name' => 'nullable|string|max:255',
                'emp_emergency_contact_relationship' => 'nullable|string|max:255',
                'emp_emergency_contact_phone' => 'nullable|string|max:20',
                'emp_emergency_contact_email' => 'nullable|email|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Prepare employee data with automatic defaults
            $employeeData = $request->only([
                'emp_title',
                'emp_first_name',
                'emp_middle_name',
                'emp_last_name',
                'emp_date_of_birth',
                'emp_job_title',
                'emp_gender',
                'emp_email_address',
                'emp_phone_number',
                'emp_xero_me_invitation',
                'emp_emergency_contact_name',
                'emp_emergency_contact_relationship',
                'emp_emergency_contact_phone',
                'emp_emergency_contact_email'
            ]);

            // Set automatic defaults
            $employeeData['emp_company_id'] = $user->company_id;
            $employeeData['emp_status'] = 'Active';

            $employee = Employee::create($employeeData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully',
                'data' => $employee->load(['company', 'currentDesignation'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing employee
     */
    public function update(Request $request, $id)
    {
        try {
            $employee = Employee::findOrFail($id);

            $validator = Validator::make($request->all(), [
                // Basic Information
                'emp_title' => 'nullable|string|max:10',
                'emp_first_name' => 'required|string|max:255',
                'emp_middle_name' => 'nullable|string|max:255',
                'emp_last_name' => 'required|string|max:255',
                'emp_date_of_birth' => 'nullable|date|before:today',
                'emp_job_title' => 'nullable|string|max:255',
                'emp_gender' => 'nullable|in:Male,Female,Other',
                
                // Contact Information
                'emp_email_address' => 'nullable|email|max:255',
                'emp_phone_number' => 'nullable|string|max:20',
                'emp_mobile_number' => 'nullable|string|max:20',
                'emp_address_search' => 'nullable|string|max:500',
                'emp_xero_me_invitation' => 'boolean',
                'emp_address_line_1' => 'nullable|string|max:255',
                'emp_address_line_2' => 'nullable|string|max:255',
                'emp_address_line_3' => 'nullable|string|max:255',
                'emp_city' => 'nullable|string|max:255',
                'emp_state' => 'nullable|string|max:255',
                'emp_post_code' => 'nullable|string|max:20',
                'emp_country' => 'nullable|string|max:255',
                
                // Employment Information
                'emp_company_id' => 'required|exists:companies,company_id',
                'emp_designation_id' => 'nullable|exists:employee_designations,id',
                'emp_status' => 'required|in:Active,Resigned,Terminated,Inactive',
                'emp_start_date' => 'nullable|date',
                'emp_resignation_date' => 'nullable|date|after:emp_start_date',
                'emp_last_working_date' => 'nullable|date|after:emp_start_date',
                'emp_termination_date' => 'nullable|date|after:emp_start_date',
                'emp_contract_end_date' => 'nullable|date|after:emp_start_date',
                'emp_tfn' => 'nullable|string|max:20',
                'emp_work_authorization' => 'nullable|in:Visa,Permanent Resident,Citizen',
                'emp_visa_subclass' => 'nullable|required_if:emp_work_authorization,Visa|date|after:today',
                'emp_visa_expiry_date' => 'nullable|required_if:emp_work_authorization,Visa|date|after:today',
                'emp_employment_type' => 'nullable|in:Contract,Part Time,Full Time,Casual',
                
                // Emergency Contact
                'emp_emergency_contact_name' => 'nullable|string|max:255',
                'emp_emergency_contact_relationship' => 'nullable|string|max:255',
                'emp_emergency_contact_phone' => 'nullable|string|max:20',
                'emp_emergency_contact_email' => 'nullable|email|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $employee->update($request->all());

            // Handle optional profile image upload in update request
            if ($request->hasFile('profile_image')) {
                // Get the authenticated user's company context
                $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
                if (!$user || !$user->company_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Company context not found. Please select a company first.'
                    ], 403);
                }

                $attachmentService = new CommonAttachmentService();
                if (!$attachmentService->validateFile($request->file('profile_image'))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid file type or size. Allowed: images up to 20MB',
                    ], 422);
                }

                $result = $attachmentService->storeAttachment(
                    'Employee',
                    $user->company_id,
                    $employee->emp_id,
                    $request->file('profile_image')
                );

                if ($result) {
                    $employee->profile_pic = $result['path'] ?? null;
                    $employee->save();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employee updated successfully',
                'data' => $employee->load(['company', 'currentDesignation'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee for editing
     */
    public function edit($id)
    {
        try {
            $employee = Employee::with(['company', 'currentDesignation'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $employee
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get employee details
     */
    public function show($id)
    {
        try {
            $employee = Employee::with(['attachments'])->findOrFail($id);

            $keys = [
                'emp_id',
                'emp_title',
                'emp_first_name',
                'emp_middle_name',
                'emp_last_name',
                'emp_date_of_birth',
                'emp_job_title',
                'emp_address_search',
                'emp_xero_me_invitation',
                'emp_emergency_contact_name',
                'emp_emergency_contact_relationship',
                'emp_emergency_contact_phone',
                'emp_emergency_contact_email',
                'emp_address_line_1',
                'emp_address_line_2',
                'emp_address_line_3',
                'emp_city',
                'emp_state',
                'emp_post_code',
                'emp_country',
            ];

            $data = Arr::only($employee->toArray(), $keys);
            // Keep only one profile image in attachments
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
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Delete employee
     */
    public function destroy($id)
    {
        try {
            $employee = Employee::findOrFail($id);
            $employee->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employee deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search employees
     */
    public function search(Request $request)
    {
        try {
            $query = Employee::with(['company', 'currentDesignation']);

            // Search by name
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('emp_first_name', 'like', "%{$search}%")
                      ->orWhere('emp_middle_name', 'like', "%{$search}%")
                      ->orWhere('emp_last_name', 'like', "%{$search}%")
                      ->orWhere('emp_job_title', 'like', "%{$search}%");
                });
            }

            // Filter by company
            if ($request->has('company_id')) {
                $query->where('emp_company_id', $request->company_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('emp_status', $request->status);
            }

            // Filter by employment type
            if ($request->has('employment_type')) {
                $query->where('emp_employment_type', $request->employment_type);
            }

            $employees = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $employees
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search employees',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload profile image for an employee
     */
    public function uploadProfileImage(Request $request, $employee_id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'profile_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // 2MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            // Validate employee belongs to user's company
            $employee = Employee::where('emp_company_id', $user->company_id)
                ->where('emp_id', $employee_id)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found or access denied',
                ], 404);
            }

            // Initialize attachment service
            $attachmentService = new CommonAttachmentService();

            // Validate file
            if (!$attachmentService->validateFile($request->file('profile_image'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file type or size. Allowed: images up to 20MB',
                ], 422);
            }

            // Store the profile image
            $result = $attachmentService->storeAttachment(
                'Employee',
                $user->company_id,
                $employee_id,
                $request->file('profile_image')
            );

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload profile image',
                ], 500);
            }

            // Persist profile picture path in employees table
            $employee->profile_pic = $result['path'] ?? null;
            $employee->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile image uploaded successfully',
                'data' => [
                    'employee_id' => $employee_id,
                    'profile_image' => $result['path'],
                    'attachment_id' => $result['id'],
                    'original_name' => $result['original_name'],
                    'size' => $result['size'],
                    'mime_type' => $result['mime_type'],
                    'profile_pic' => $employee->profile_pic
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get profile image for an employee
     */
    public function getProfileImage($employee_id): JsonResponse
    {
        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            // Validate employee belongs to user's company
            $employee = Employee::where('emp_company_id', $user->company_id)
                ->where('emp_id', $employee_id)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found or access denied',
                ], 404);
            }

            // Initialize attachment service
            $attachmentService = new CommonAttachmentService();

            // Get attachments for this employee
            $attachments = $attachmentService->getAttachments('Employee', $employee_id);

            // Filter for profile images (you might want to add a specific type field for profile images)
            $profileImages = array_filter($attachments, function($attachment) {
                return in_array($attachment['mime_type'], ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Profile images retrieved successfully',
                'data' => [
                    'employee_id' => $employee_id,
                    'attachments' => array_values($profileImages)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all attachments for an employee
     */
    public function getAttachments($employee_id): JsonResponse
    {
        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            // Validate employee belongs to user's company
            $employee = Employee::where('emp_company_id', $user->company_id)
                ->where('emp_id', $employee_id)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found or access denied',
                ], 404);
            }

            // Initialize attachment service
            $attachmentService = new CommonAttachmentService();

            // Get all attachments for this employee
            $attachments = $attachmentService->getAttachments('Employee', $employee_id);

            return response()->json([
                'success' => true,
                'message' => 'Attachments retrieved successfully',
                'data' => [
                    'employee_id' => $employee_id,
                    'attachments' => $attachments,
                    'total_count' => count($attachments)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attachments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete profile image for an employee
     */
    public function deleteProfileImage(Request $request, $employee_id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'attachment_id' => 'required|integer|exists:attachments,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            // Validate employee belongs to user's company
            $employee = Employee::where('emp_company_id', $user->company_id)
                ->where('emp_id', $employee_id)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found or access denied',
                ], 404);
            }

            // Initialize attachment service
            $attachmentService = new CommonAttachmentService();

            // Delete the attachment
            $deleted = $attachmentService->deleteAttachment('Employee', $employee_id, $request->attachment_id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete profile image',
                ], 500);
            }

            // Profile image deleted successfully

            return response()->json([
                'success' => true,
                'message' => 'Profile image deleted successfully',
                'data' => [
                    'employee_id' => $employee_id,
                    'deleted_attachment_id' => $request->attachment_id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete profile image',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
