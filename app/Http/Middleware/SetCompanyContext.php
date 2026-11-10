<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('api')->user();
        
        if ($user) {
            // Get company context from cache using the token
            $token = $request->bearerToken();
            $companyId = null;
            
            if ($token) {
                $companyId = \Cache::get("user_company_{$token}");
            }
            
            if ($companyId) {
                // Verify that the company exists and user has access to it
                $company = \App\Models\Company::where('company_id', $companyId)
                    ->where('user_id', $user->id)
                    ->first();
                
                if ($company) {
                    // Set the company_id as a dynamic property on the user object
                    $user->company_id = $companyId;
                    
                    // Also add it to the request for easy access
                    $request->merge(['company_id' => $companyId]);
                    
                    \Log::info('Company context set from cache', [
                        'user_id' => $user->id,
                        'company_id' => $companyId,
                        'company_name' => $company->company_name
                    ]);
                }
            }
        }

        return $next($request);
    }
}
