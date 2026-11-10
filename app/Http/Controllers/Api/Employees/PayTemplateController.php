<?php

namespace App\Http\Controllers\Api\Employees;

use App\Http\Controllers\Controller;
use App\Models\PayTemplate;
use App\Models\PayTemplateEarning;
use App\Models\PayTemplateAuditLog;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayTemplateController extends Controller
{
    /**
     * Store new pay template with earnings for an employee
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,emp_id',
            'template_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,draft',
            'earnings' => 'required|array|min:1|max:20',
            'earnings.*.earnings_rate_name' => 'required|string|max:255',
            'earnings.*.calculation_type' => 'required|in:hours_rate,fixed_amount,mixed',
            'earnings.*.estimate_rate' => 'nullable|numeric|min:0|max:999999.999999',
            'earnings.*.hours' => 'nullable|numeric|min:0|max:999999.9999',
            'earnings.*.rate' => 'nullable|numeric|min:0|max:999999.999999',
            'earnings.*.total' => 'nullable|numeric|min:0|max:999999999.99',
            'earnings.*.fixed_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'earnings.*.mixed_fixed_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'earnings.*.mixed_rate' => 'nullable|numeric|min:0|max:999999.999999',
            'earnings.*.mixed_total' => 'nullable|numeric|min:0|max:999999999.99',
            'earnings.*.is_active' => 'boolean',
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

            // Create pay template
            $templateData = $request->only(['employee_id', 'template_name', 'description', 'status']);
            $templateData['status'] = $templateData['status'] ?? 'active';
            $templateData['created_by'] = $user->id;
            $templateData['updated_by'] = $user->id;

            $payTemplate = PayTemplate::create($templateData);

            // Log template creation
            PayTemplateAuditLog::logCreation(
                PayTemplateAuditLog::TABLE_PAY_TEMPLATES,
                $payTemplate->pay_template_id,
                $payTemplate->toArray()
            );

            // Create earnings
            $createdEarnings = [];
            foreach ($request->earnings as $index => $earningData) {
                $earningData['pay_template_id'] = $payTemplate->pay_template_id;
                $earningData['display_order'] = $index;
                $earningData['is_active'] = $earningData['is_active'] ?? true;
                $earningData['created_by'] = $user->id;
                $earningData['updated_by'] = $user->id;

                $earning = PayTemplateEarning::create($earningData);
                
                // Calculate total if not provided
                if (!isset($earningData['total']) || empty($earningData['total'])) {
                    $total = $this->calculateEarningTotal($earningData);
                    $earning->update(['total' => $total]);
                }

                $createdEarnings[] = $earning;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pay template created successfully',
                'data' => [
                    'template' => $payTemplate->load('employee'),
                    'earnings' => $createdEarnings
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create pay template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display pay template for a specific employee
     */
    public function show(string $pay_template_id): JsonResponse
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

            $payTemplate = PayTemplate::with(['employee', 'earnings'])
                ->where('pay_template_id', $pay_template_id)
                ->whereHas('employee', function ($query) use ($user) {
                    $query->where('emp_company_id', $user->company_id);
                })
                ->first();

            if (!$payTemplate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pay template not found or access denied',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pay template retrieved successfully',
                'data' => $payTemplate,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pay template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update pay template for a specific employee
     */
    public function update(Request $request, string $pay_template_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'template_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,draft',
            'earnings' => 'nullable|array|min:1|max:20',
            'earnings.*.earnings_rate_name' => 'nullable|string|max:255',
            'earnings.*.calculation_type' => 'nullable|in:hours_rate,fixed_amount,mixed',
            'earnings.*.estimate_rate' => 'nullable|numeric|min:0|max:999999.999999',
            'earnings.*.hours' => 'nullable|numeric|min:0|max:999999.9999',
            'earnings.*.rate' => 'nullable|numeric|min:0|max:999999.999999',
            'earnings.*.total' => 'nullable|numeric|min:0|max:999999999.99',
            'earnings.*.fixed_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'earnings.*.mixed_fixed_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'earnings.*.mixed_rate' => 'nullable|numeric|min:0|max:999999.999999',
            'earnings.*.mixed_total' => 'nullable|numeric|min:0|max:999999999.99',
            'earnings.*.is_active' => 'boolean',
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

            $payTemplate = PayTemplate::where('pay_template_id', $pay_template_id)
                ->whereHas('employee', function ($query) use ($user) {
                    $query->where('emp_company_id', $user->company_id);
                })
                ->first();

            if (!$payTemplate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pay template not found or access denied',
                ], 404);
            }

            // Update template
            $templateData = $request->only(['template_name', 'description', 'status']);
            $templateData['updated_by'] = $user->id;
            $payTemplate->update($templateData);

            // Log template update
            PayTemplateAuditLog::logUpdate(
                PayTemplateAuditLog::TABLE_PAY_TEMPLATES,
                $payTemplate->pay_template_id,
                $payTemplate->toArray()
            );

            // Update earnings if provided
            if ($request->has('earnings')) {
                // Delete existing earnings
                PayTemplateEarning::where('pay_template_id', $pay_template_id)->delete();

                // Create new earnings
                $updatedEarnings = [];
                foreach ($request->earnings as $index => $earningData) {
                    $earningData['pay_template_id'] = $payTemplate->pay_template_id;
                    $earningData['display_order'] = $index;
                    $earningData['is_active'] = $earningData['is_active'] ?? true;
                    $earningData['created_by'] = $user->id;
                    $earningData['updated_by'] = $user->id;

                    $earning = PayTemplateEarning::create($earningData);
                    
                    // Calculate total if not provided
                    if (!isset($earningData['total']) || empty($earningData['total'])) {
                        $total = $this->calculateEarningTotal($earningData);
                        $earning->update(['total' => $total]);
                    }

                    $updatedEarnings[] = $earning;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pay template updated successfully',
                'data' => [
                    'template' => $payTemplate->load('employee'),
                    'earnings' => $updatedEarnings ?? $payTemplate->earnings
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update pay template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate earning total based on calculation type
     */
    private function calculateEarningTotal(array $earningData): float
    {
        $calculationType = $earningData['calculation_type'] ?? 'hours_rate';
        
        switch ($calculationType) {
            case 'hours_rate':
                $hours = $earningData['hours'] ?? 0;
                $rate = $earningData['rate'] ?? 0;
                return $hours * $rate;
                
            case 'fixed_amount':
                return $earningData['fixed_amount'] ?? 0;
                
            case 'mixed':
                $fixedAmount = $earningData['mixed_fixed_amount'] ?? 0;
                $mixedHours = $earningData['hours'] ?? 0;
                $mixedRate = $earningData['mixed_rate'] ?? 0;
                return $fixedAmount + ($mixedHours * $mixedRate);
                
            default:
                return 0;
        }
    }
}
