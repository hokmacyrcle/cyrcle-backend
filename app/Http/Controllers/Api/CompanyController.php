<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    /**
     * Get all companies for authenticated user
     */
    public function getUserCompanies(Request $request): JsonResponse
    {
        $user = $request->user();
        $companies = Company::where('user_id', $user->id)
            ->select('company_id', 'company_name', 'company_type', 'company_abn', 'company_acn')
            ->get();
            
        if ($companies->isEmpty()) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'No companies found for this user'
            ], 404);
        }
        
        return response()->json([
            'status' => true,
            'status_code' => 200,
            'message' => 'Companies information retrieved successfully',
            'data' => [
                'companies' => $companies,
                'companies_count' => $companies->count(),
                'total_companies' => $companies->count()
            ]
        ], 200);
    }

    /**
     * Select a company and set company context in current token
     */
    public function selectCompany(Request $request, $company_id): JsonResponse
    {
        $user = $request->user();
        
        // Validate company_id parameter
        if (!is_numeric($company_id) || !$company_id) {
            return response()->json([
                'status' => false,
                'status_code' => 400,
                'message' => 'Invalid company ID provided'
            ], 400);
        }
        
        $companyId = (int) $company_id;
        
        // Check if user has access to this company
        $company = Company::where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$company) {
            return response()->json([
                'status' => false,
                'status_code' => 403,
                'message' => 'You do not have access to this company'
            ], 403);
        }
        
        // Store company context in the user's session/request for this token
        // We'll use a simple approach: store company_id in the user object
        $user->company_id = $companyId;
        
        // Also store in cache for this user's token
        $token = request()->bearerToken();
        if ($token) {
            \Cache::put("user_company_{$token}", $companyId, now()->addDays(30));
        }
        
        // Return success response with company info (no new token needed)
        return response()->json([
            'status' => true,
            'status_code' => 200,
            'message' => 'Company selected successfully',
            'data' => [
                'user' => $user->fresh(),
                'selected_company' => [
                    'company_id' => $company->company_id,
                    'company_name' => $company->company_name,
                    'company_type' => $company->company_type,
                    'company_abn' => $company->company_abn,
                    'company_acn' => $company->company_acn
                ],
                'company_context' => true,
                'message' => 'Use the same token for all subsequent requests'
            ]
        ], 200);
    }

    /**
     * Get currently selected company for authenticated user
     */
    public function getCurrentCompany(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = request()->bearerToken();
        
        if (!$token) {
            return response()->json([
                'status' => false,
                'status_code' => 400,
                'message' => 'No authentication token provided'
            ], 400);
        }
        
        $companyId = \Cache::get("user_company_{$token}");
        
        if (!$companyId) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'No company selected. Please select a company first.'
            ], 404);
        }
        
        $company = Company::where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$company) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Selected company not found or access denied'
            ], 404);
        }
        
        return response()->json([
            'status' => true,
            'status_code' => 200,
            'message' => 'Current company retrieved successfully',
            'data' => [
                'company' => [
                    'company_id' => $company->company_id,
                    'company_name' => $company->company_name,
                    'company_type' => $company->company_type,
                    'company_abn' => $company->company_abn,
                    'company_acn' => $company->company_acn
                ]
            ]
        ], 200);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $query = \App\Models\Company::with('locations')->where('user_id', $request->user()->id);
        if ($search = $request->query('search')) {
            $query->where('company_name', 'like', "%{$search}%");
        }
        return response()->json(['success' => true, 'data' => $query->orderBy('company_id')->paginate($perPage)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_abn' => 'nullable|digits:11',
            'company_acn' => 'nullable|digits:9',
            'gst_registered' => 'nullable|boolean',
            'gst_number' => 'nullable|string|max:50',
            'gst_registration_start_date' => 'nullable|date',
            'company_registration_date' => 'nullable|date',
            'company_type' => 'nullable|string|max:50',
        ]);
        $data['user_id'] = $request->user()->id;
        $company = \App\Models\Company::create($data);
        return response()->json(['success' => true, 'data' => $company], 201);
    }

    public function show(Request $request, $company): JsonResponse
    {
        $row = \App\Models\Company::where('user_id', $request->user()->id)->where('company_id', $company)->firstOrFail();
        return response()->json(['success' => true, 'data' => $row]);
    }

    public function update(Request $request, $company): JsonResponse
    {
        $row = \App\Models\Company::where('user_id', $request->user()->id)->where('company_id', $company)->firstOrFail();
        $data = $request->validate([
            'company_name' => 'sometimes|required|string|max:255',
            'company_abn' => 'nullable|digits:11',
            'company_acn' => 'nullable|digits:9',
            'gst_registered' => 'nullable|boolean',
            'gst_number' => 'nullable|string|max:50',
            'gst_registration_start_date' => 'nullable|date',
            'company_registration_date' => 'nullable|date',
            'company_type' => 'nullable|string|max:50',
        ]);
        $row->update($data);
        return response()->json(['success' => true, 'data' => $row]);
    }

    public function destroy(Request $request, $company): JsonResponse
    {
        $row = \App\Models\Company::where('user_id', $request->user()->id)->where('company_id', $company)->firstOrFail();
        $row->delete();
        return response()->json(['success' => true]);
    }}
