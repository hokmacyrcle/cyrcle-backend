<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        // Validate required fields
        $errors = [];
        
        if (!$request->email) {
            $errors['email'] = 'Email is required';
        }
        
        if (!$request->first_name) {
            $errors['first_name'] = 'First name is required';
        }
        
        if (!$request->last_name) {
            $errors['last_name'] = 'Last name is required';
        }
        
        if (!$request->password) {
            $errors['password'] = 'Password is required';
        }
        
        if (!$request->password_confirmation) {
            $errors['password_confirmation'] = 'Password confirmation is required';
        }
        
        if ($request->password && $request->password_confirmation && $request->password !== $request->password_confirmation) {
            $errors['password'] = 'Password confirmation does not match';
        }
        
        // Check if email already exists
        // An abandoned sign-up (email never verified, no firm, created in the last 24h) can be retried with the same email.
        // Older unverified accounts are legacy users and are never overwritten.
        $retry = null;
        if ($request->email) {
            $existing = User::where('email', $request->email)->first();
            if ($existing) {
                $hasFirm = \App\Models\Company::where('user_id', $existing->id)->exists();
                if ($existing->email_verified_at === null && !$hasFirm && ($existing->role ?? 'employer') === 'employer'
                    && $existing->created_at && $existing->created_at->gt(now()->subDay())) {
                    $retry = $existing;
                } else {
                    $errors['email'] = 'This email is already registered';
                }
            }
        }
        
        // Check if phone already exists (if provided)
        if ($request->phone && $request->country_code) {
            if (User::where('phone', $request->phone)->where('country_code', $request->country_code)->when($retry, fn ($q) => $q->where('id', '!=', $retry->id))->exists()) {
                $errors['phone'] = 'This phone number is already registered for this country';
            }
        }
        
        // If there are validation errors, return them
        if (!empty($errors)) {
            return response()->json([
                'status' => false,
                'status_code' => 400,
                'message' => 'Validation failed',
                'errors' => $errors
            ], 200);
        }
        
        // Create full name from first, middle, and last name
        $fullName = trim($request->first_name . ' ' . ($request->middle_name ?? '') . ' ' . $request->last_name);
        
        $fields = [
            'name' => $fullName,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'country_code' => $request->country_code,
            'password' => Hash::make($request->password),
            'marketing_opt_in' => (bool) $request->marketing_opt_in,
        ];
        if ($retry) {
            $retry->update($fields);
            $user = $retry;
        } else {
        $user = User::create([
            'name' => $fullName,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'country_code' => $request->country_code,
            'password' => Hash::make($request->password),
            'marketing_opt_in' => (bool) $request->marketing_opt_in,
        ]);
        }

        $token = $user->createToken('AuthToken')->accessToken;

        return response()->json([
            'status' => true,
            'status_code' => 201,
            'message' => 'User registered successfully',
            'data' => [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

    /**
     * Login user and create token
     */
    public function login(Request $request)
    {
        // Simple validation
        if (!$request->email || !$request->password) {
            return response()->json([
                'status' => false,
                'status_code' => 400,
                'message' => 'Email and password are required'
            ], 200);
        }

        // Use web guard for authentication attempt
        if (!Auth::guard('web')->attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => false,
                'status_code' => 401,
                'message' => 'Invalid credentials'
            ], 200);
        }

        $user = Auth::guard('web')->user();
        $token = $user->createToken('AuthToken')->accessToken;

        // Get all companies based on user_id
        $companies = [];
        if ($user->id) {
            $companies = \App\Models\Company::where('user_id', $user->id)
                ->select('company_id', 'company_name', 'company_type', 'company_abn', 'company_acn')
                ->get();
        }

        return response()->json([
            'status' => true,
            'status_code' => 200,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'companies' => $companies,
                'companies_count' => $companies->count(),
                'firm_status' => \App\Models\Company::where('user_id', $user->id)->where('company_type', 'Subscriber')->value('status'),
                'email_verified' => (bool) $user->email_verified_at,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 200);
    }

    /**
     * Logout user (Revoke the token)
     */
    public function logout(Request $request)
    {
        try {
            // Get the authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'status_code' => 401,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Get the token that was used for this request
            $token = $user->token();
            
            if ($token) {
                // Log token before revocation
                \Log::info('Revoking token', ['token_id' => $token->id, 'user_id' => $user->id]);
                
                // Revoke the token using Passport's method
                $token->revoke();
                
                // Double-check by marking it as revoked in the database
                \DB::table('oauth_access_tokens')
                    ->where('id', $token->id)
                    ->update([
                        'revoked' => true,
                        'updated_at' => now()
                    ]);
                
                // Log after revocation
                \Log::info('Token revoked successfully', ['token_id' => $token->id]);
                
                // Revoke ALL tokens for this user (optional - uncomment if you want to logout from all devices)
                // $user->tokens()->update(['revoked' => true]);
            }

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Successfully logged out'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Logout error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        
        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'status' => false,
                'status_code' => 401,
                'message' => 'User not authenticated',
                'error' => 'No authenticated user found'
            ], 200);
        }
        
        // Check if user has a token
        if (!$user->token()) {
            return response()->json([
                'status' => false,
                'status_code' => 400,
                'message' => 'No active token found',
                'error' => 'User does not have an active access token'
            ], 200);
        }
        
        // Revoke the current token
        $user->token()->revoke();
        
        // Create a new token
        $token = $user->createToken('AuthToken')->accessToken;

        return response()->json([
            'status' => true,
            'status_code' => 200,
            'message' => 'Token refreshed successfully',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 200);
    }

    /**
     * Update user profile
     */
    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $data = $request->only(['first_name', 'middle_name', 'last_name', 'email', 'phone', 'country_code']);
        
        // Update full name if any name fields are changed
        if (isset($data['first_name']) || isset($data['middle_name']) || isset($data['last_name'])) {
            $firstName = $data['first_name'] ?? $user->first_name;
            $middleName = $data['middle_name'] ?? $user->middle_name;
            $lastName = $data['last_name'] ?? $user->last_name;
            $data['name'] = trim($firstName . ' ' . ($middleName ?? '') . ' ' . $lastName);
        }
        
        $user->update($data);

        return response()->json([
            'status' => true,
            'status_code' => 200,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => $user->fresh(),
            ]
        ], 200);
    }
} 