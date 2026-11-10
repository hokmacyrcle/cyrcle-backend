<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTerm;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PaymentTermController extends Controller
{
    /**
     * Display a listing of payment terms.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PaymentTerm::query();

            // Apply filters
            if ($request->has('due_calculation_method')) {
                $query->where('due_calculation_method', $request->due_calculation_method);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where('name', 'like', "%{$search}%");
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            $paymentTerms = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $paymentTerms,
                'message' => 'Payment terms retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment terms: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created payment term.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'due_calculation_method' => 'required|in:fixed_days,certain_day',
                'fixed_days_value' => 'required_if:due_calculation_method,fixed_days|nullable|integer|min:1|max:365',
                'certain_day_value' => 'required_if:due_calculation_method,certain_day|nullable|integer|min:1|max:31',
                'next_month_threshold_days' => 'nullable|integer|min:1|max:31',
                'is_active' => 'boolean',
                'description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $paymentTerm = PaymentTerm::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $paymentTerm,
                'message' => 'Payment term created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating payment term: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified payment term.
     */
    public function show($id): JsonResponse
    {
        try {
            $paymentTerm = PaymentTerm::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $paymentTerm,
                'message' => 'Payment term retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment term not found'
            ], 404);
        }
    }

    /**
     * Update the specified payment term.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $paymentTerm = PaymentTerm::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'due_calculation_method' => 'sometimes|required|in:fixed_days,certain_day',
                'fixed_days_value' => 'required_if:due_calculation_method,fixed_days|nullable|integer|min:1|max:365',
                'certain_day_value' => 'required_if:due_calculation_method,certain_day|nullable|integer|min:1|max:31',
                'next_month_threshold_days' => 'nullable|integer|min:1|max:31',
                'is_active' => 'boolean',
                'description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $paymentTerm->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $paymentTerm->fresh(),
                'message' => 'Payment term updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating payment term: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified payment term.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $paymentTerm = PaymentTerm::findOrFail($id);
            $paymentTerm->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payment term deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting payment term: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active payment terms for dropdown.
     */
    public function getActivePaymentTerms(): JsonResponse
    {
        try {
            $paymentTerms = PaymentTerm::active()
                ->select('id', 'name', 'due_calculation_method', 'fixed_days_value', 'certain_day_value')
                ->orderBy('name')
                ->get()
                ->map(function ($paymentTerm) {
                    return [
                        'id' => $paymentTerm->id,
                        'text' => $paymentTerm->display_name,
                        'due_calculation_method' => $paymentTerm->due_calculation_method,
                        'fixed_days_value' => $paymentTerm->fixed_days_value,
                        'certain_day_value' => $paymentTerm->certain_day_value,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $paymentTerms,
                'message' => 'Active payment terms retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving active payment terms: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate due date for a payment term.
     */
    public function calculateDueDate(Request $request, $id): JsonResponse
    {
        try {
            $paymentTerm = PaymentTerm::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'invoice_date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $dueDate = $paymentTerm->calculateDueDate($request->invoice_date);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_term' => $paymentTerm->name,
                    'invoice_date' => $request->invoice_date,
                    'calculated_due_date' => $dueDate->format('Y-m-d'),
                    'days_difference' => $dueDate->diffInDays($request->invoice_date),
                ],
                'message' => 'Due date calculated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating due date: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle payment term status.
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $paymentTerm = PaymentTerm::findOrFail($id);
            $paymentTerm->update(['is_active' => !$paymentTerm->is_active]);

            return response()->json([
                'success' => true,
                'data' => $paymentTerm->fresh(),
                'message' => 'Payment term status toggled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error toggling payment term status: ' . $e->getMessage()
            ], 500);
        }
    }
}
