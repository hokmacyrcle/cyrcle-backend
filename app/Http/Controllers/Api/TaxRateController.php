<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TaxRateController extends Controller
{
    /**
     * Display a listing of tax rates.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = TaxRate::query();

            // Apply filters
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('tax_name', 'like', "%{$search}%")
                      ->orWhere('agency_name', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'tax_name');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            $taxRates = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $taxRates,
                'message' => 'Tax rates retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving tax rates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created tax rate.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'tax_name' => 'required|string|max:255',
                'agency_name' => 'nullable|string|max:255',
                'rate' => 'required|numeric|min:0|max:100',
                'type' => 'required|in:single,combined',
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

            $taxRate = TaxRate::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $taxRate,
                'message' => 'Tax rate created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating tax rate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified tax rate.
     */
    public function show($id): JsonResponse
    {
        try {
            $taxRate = TaxRate::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $taxRate,
                'message' => 'Tax rate retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tax rate not found'
            ], 404);
        }
    }

    /**
     * Update the specified tax rate.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {

            $taxRate = TaxRate::findOrFail($id);
            $validator = Validator::make($request->all(), [
                'tax_name' => 'sometimes|required|string|max:255',
                'agency_name' => 'nullable|string|max:255',
                'rate' => 'sometimes|required|numeric|min:0|max:100',
                'type' => 'sometimes|required|in:single,combined',
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

            $taxRate->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $taxRate->fresh(),
                'message' => 'Tax rate updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating tax rate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified tax rate.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $taxRate = TaxRate::findOrFail($id);
            $taxRate->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tax rate deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting tax rate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active tax rates for dropdown.
     */
    public function getActiveTaxRates(): JsonResponse
    {
        try {
            $taxRates = TaxRate::active()
                ->select('id', 'tax_name', 'rate', 'type')
                ->orderBy('tax_name')
                ->get()
                ->map(function ($taxRate) {
                    return [
                        'id' => $taxRate->id,
                        'text' => $taxRate->display_name,
                        'rate' => $taxRate->rate,
                        'type' => $taxRate->type,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $taxRates,
                'message' => 'Active tax rates retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving active tax rates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle tax rate status.
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $taxRate = TaxRate::findOrFail($id);
            $taxRate->update(['is_active' => !$taxRate->is_active]);

            return response()->json([
                'success' => true,
                'data' => $taxRate->fresh(),
                'message' => 'Tax rate status toggled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error toggling tax rate status: ' . $e->getMessage()
            ], 500);
        }
    }
}
