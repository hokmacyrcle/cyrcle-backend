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
}
