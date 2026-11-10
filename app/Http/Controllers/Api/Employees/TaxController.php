<?php

namespace App\Http\Controllers\Api\Employees;

use App\Http\Controllers\Controller;
use App\Models\TaxDetail;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TaxController extends Controller
{
    /**
     * Store new tax details for an employee
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,emp_id',
            'tax_file_number' => 'nullable|string|max:9|regex:/^[0-9]{9}$/',
            'tfn_exemption' => 'nullable|in:Working holiday maker,Student,Pensioner,Other',
            'residency_status' => 'required|in:Australian resident,Foreign resident',
            'has_approved_withholding_variation' => 'boolean',
            'tax_scale_type' => 'required|in:Regular,No TFN,Working holiday maker,Student,Pensioner',
            'has_study_training_loans' => 'boolean',
            'claim_tax_free_threshold' => 'boolean',
            'other_tax_offset_claimed' => 'boolean',
            'tax_offset_amount' => 'nullable|numeric|min:0|max:999999.99',
            'increase_tax_withheld' => 'boolean',
            'additional_tax_withheld' => 'nullable|numeric|min:0|max:999999.99',
            'eligible_for_leave_loading' => 'boolean',
            'tax_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

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
                ->where('emp_id', $request->employee_id)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found or access denied',
                ], 404);
            }

            // Check if tax details already exist for this employee
            $existingTaxDetails = TaxDetail::where('employee_id', $request->employee_id)->first();

            if ($existingTaxDetails) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tax details already exist for this employee. Use update instead.',
                    'data' => $existingTaxDetails->load('employee'),
                ], 400);
            }

            $taxDetails = TaxDetail::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Tax details created successfully',
                'data' => $taxDetails->load('employee'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tax details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display tax details for a specific employee
     */
    public function show(string $employee_id): JsonResponse
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

            $taxDetails = TaxDetail::where('employee_id', $employee_id)->first();

            if (!$taxDetails) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tax details not found for this employee',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tax details retrieved successfully',
                'data' => $taxDetails->load('employee'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tax details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update tax details for a specific employee
     */
    public function update(Request $request, string $employee_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tax_file_number' => 'nullable|string|max:9|regex:/^[0-9]{9}$/',
            'tfn_exemption' => 'nullable|in:Working holiday maker,Student,Pensioner,Other',
            'residency_status' => 'nullable|in:Australian resident,Foreign resident',
            'has_approved_withholding_variation' => 'boolean',
            'tax_scale_type' => 'nullable|in:Regular,No TFN,Working holiday maker,Student,Pensioner',
            'has_study_training_loans' => 'boolean',
            'claim_tax_free_threshold' => 'boolean',
            'other_tax_offset_claimed' => 'boolean',
            'tax_offset_amount' => 'nullable|numeric|min:0|max:999999.99',
            'increase_tax_withheld' => 'boolean',
            'additional_tax_withheld' => 'nullable|numeric|min:0|max:999999.99',
            'eligible_for_leave_loading' => 'boolean',
            'tax_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

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

            $taxDetails = TaxDetail::where('employee_id', $employee_id)->first();

            if (!$taxDetails) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tax details not found for this employee',
                ], 404);
            }

            $taxDetails->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Tax details updated successfully',
                'data' => $taxDetails->load('employee'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tax details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
