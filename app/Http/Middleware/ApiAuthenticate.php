<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if Authorization header exists
        if (!$request->hasHeader('Authorization')) {
            return response()->json([
                'status' => false,
                'status_code' => 401,
                'message' => 'Access token is missing',
                'error' => 'Authorization header is required. Please provide a valid Bearer token.'
            ], 200);
        }

        // Check if Authorization header starts with 'Bearer '
        $authHeader = $request->header('Authorization');
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'status' => false,
                'status_code' => 401,
                'message' => 'Invalid token format',
                'error' => 'Authorization header must start with "Bearer ". Example: Authorization: Bearer {your_token}'
            ], 200);
        }

        // Extract token
        $token = substr($authHeader, 7); // Remove 'Bearer ' prefix

        // Check if token is empty
        if (empty($token)) {
            return response()->json([
                'status' => false,
                'status_code' => 401,
                'message' => 'Token is empty',
                'error' => 'Access token cannot be empty. Please provide a valid Bearer token.'
            ], 200);
        }

        // Try to authenticate with the token using Passport
        try {
            // Use Passport's token authentication
            $user = Auth::guard('api')->user();
            
            // If no user found, try to authenticate with the token
            if (!$user) {
                // Try to authenticate with the API guard
                if (!Auth::guard('api')->check()) {
                    return response()->json([
                        'status' => false,
                        'status_code' => 401,
                        'message' => 'Invalid or expired token',
                        'error' => 'The provided access token is invalid or has expired. Please login again to get a new token.'
                    ], 200);
                }
                
                $user = Auth::guard('api')->user();
            }
            
            // Ensure the user is set on the request
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'status_code' => 401,
                    'message' => 'User not found',
                    'error' => 'Unable to retrieve user information from the token.'
                ], 200);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 401,
                'message' => 'Authentication failed',
                'error' => 'Failed to authenticate with the provided token. Please login again.'
            ], 200);
        }

        return $next($request);
    }
}
