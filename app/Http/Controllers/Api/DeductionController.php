<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deduction;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeductionController extends Controller
{
    /**
     * Store new deduction for an employee
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,emp_id',
            'deduction_type' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0|max:999999999.99',
            'deduction_category' => 'nullable|in:pre_tax,post_tax,garnishment,other',
            'calculation_type' => 'nullable|in:fixed_amount,percentage,per_pay_period',
            'percentage_rate' => 'nullable|numeric|min:0|max:100|required_if:calculation_type,percentage',
            'frequency' => 'nullable|in:per_pay_period,monthly,quarterly,annually',
            'status' => 'nullable|in:active,inactive,suspended',
            'is_required' => 'boolean',
            'effective_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:effective_date',
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

            // Check if deduction type already exists for this employee
            $existingDeduction = Deduction::where('employee_id', $request->employee_id)
                ->where('deduction_type', $request->deduction_type)
                ->first();

            if ($existingDeduction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Deduction type already exists for this employee',
                    'data' => $existingDeduction,
                ], 409);
            }

            // Prepare deduction data
            $deductionData = $request->all();
            $deductionData['status'] = $deductionData['status'] ?? 'active';
            $deductionData['deduction_category'] = $deductionData['deduction_category'] ?? 'pre_tax';
            $deductionData['calculation_type'] = $deductionData['calculation_type'] ?? 'fixed_amount';
            $deductionData['frequency'] = $deductionData['frequency'] ?? 'per_pay_period';
            $deductionData['is_required'] = $deductionData['is_required'] ?? false;
            $deductionData['created_by'] = $user->id;
            $deductionData['updated_by'] = $user->id;

            // Create deduction
            $deduction = Deduction::create($deductionData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Deduction created successfully',
                'data' => $deduction,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create deduction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display deductions for a specific employee
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

            // Get deductions for this employee
            $deductions = Deduction::where('employee_id', $employee_id)->get();

            if ($deductions->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No deductions found for this employee',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Deductions retrieved successfully',
                'data' => $deductions,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve deductions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update deduction for a specific deduction ID
     */
    public function update(Request $request, string $deduction_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'deduction_type' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0|max:999999999.99',
            'deduction_category' => 'nullable|in:pre_tax,post_tax,garnishment,other',
            'calculation_type' => 'nullable|in:fixed_amount,percentage,per_pay_period',
            'percentage_rate' => 'nullable|numeric|min:0|max:100|required_if:calculation_type,percentage',
            'frequency' => 'nullable|in:per_pay_period,monthly,quarterly,annually',
            'status' => 'nullable|in:active,inactive,suspended',
            'is_required' => 'boolean',
            'effective_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:effective_date',
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

            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            // Find deduction with employee validation
            $deduction = Deduction::with('employee')->find($deduction_id);

            if (!$deduction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Deduction not found',
                ], 404);
            }

            // Validate employee belongs to user's company
            if ($deduction->employee->emp_company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this deduction',
                ], 403);
            }

            // Update deduction
            $data = $request->all();
            $data['updated_by'] = $user->id;
            
            $deduction->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Deduction updated successfully',
                'data' => $deduction,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update deduction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
