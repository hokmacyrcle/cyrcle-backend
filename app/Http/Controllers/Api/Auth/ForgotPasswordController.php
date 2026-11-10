<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ForgotPasswordOTP;
use App\Models\PasswordResetOTP;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordController extends Controller
{
    /**
     * Send OTP to user's email for password reset
     */
    public function sendOTP(Request $request)
    {
        // Validate email
        if (!$request->email) {
            return response()->json([
                'status' => false,
                'status_code' => 400,
                'message' => 'Email is required'
            ], 200);
        }

        // Check if user exists
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'User not found with this email address'
            ], 200);
        }

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Set expiration time (5 minutes from now)
        $expiresAt = now()->addMinutes(5);

        // Delete any existing OTPs for this email
        PasswordResetOTP::where('email', $request->email)->delete();

        // Create new OTP record
        PasswordResetOTP::create([
            'email' => $request->email,
            'otp' => $otp,
            'expires_at' => $expiresAt,
            'used' => false,
        ]);

        // Send email with OTP
        try {
            Mail::to($request->email)->send(new ForgotPasswordOTP($otp, $user->first_name));
            
            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'OTP sent successfully to your email address',
                'data' => [
                    'email' => $request->email,
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s')
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to send OTP. Please try again later.'
            ], 200);
        }
    }

    /**
     * Verify OTP only
     */
    public function verifyOTP(Request $request)
    {
        // Validate required fields
        $errors = [];
        
        if (!$request->email) {
            $errors['email'] = 'Email is required';
        }
        
        if (!$request->otp) {
            $errors['otp'] = 'OTP is required';
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

        // Check if user exists
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'User not found with this email address'
            ], 200);
        }

        // Find the OTP record
        $otpRecord = PasswordResetOTP::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('used', false)
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'status' => false,
                'status_code' => 400,
                'message' => 'Invalid or expired OTP'
            ], 200);
        }

        // Check if OTP is expired
        if ($otpRecord->isExpired()) {
            return response()->json([
                'status' => false,
                'status_code' => 400,
                'message' => 'OTP has expired. Please request a new one.'
            ], 200);
        }

        // Mark OTP as used
        $otpRecord->markAsUsed();

        return response()->json([
            'status' => true,
            'status_code' => 200,
            'message' => 'OTP verified successfully',
            'data' => [
                'email' => $request->email,
                'verified_at' => now()->format('Y-m-d H:i:s')
            ]
        ], 200);
    }

    /**
     * Reset password after OTP verification
     */
    public function resetPassword(Request $request)
    {
        // Validate required fields
        $errors = [];
        
        if (!$request->email) {
            $errors['email'] = 'Email is required';
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

        // If there are validation errors, return them
        if (!empty($errors)) {
            return response()->json([
                'status' => false,
                'status_code' => 400,
                'message' => 'Validation failed',
                'errors' => $errors
            ], 200);
        }

        // Check if user exists
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'User not found with this email address'
            ], 200);
        }

        // Check if OTP was verified (used)
        $otpRecord = PasswordResetOTP::where('email', $request->email)
            ->where('used', true)
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'status' => false,
                'status_code' => 400,
                'message' => 'OTP verification required. Please verify OTP first.'
            ], 200);
        }

        // Update user's password
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // Delete all OTPs for this email
        PasswordResetOTP::where('email', $request->email)->delete();

        return response()->json([
            'status' => true,
            'status_code' => 200,
            'message' => 'Password reset successfully'
        ], 200);
    }

    /**
     * Resend OTP to user's email
     */
    public function resendOTP(Request $request)
    {
        // Validate email
        if (!$request->email) {
            return response()->json([
                'status' => false,
                'status_code' => 400,
                'message' => 'Email is required'
            ], 200);
        }

        // Check if user exists
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'User not found with this email address'
            ], 200);
        }

        // Generate new 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Set expiration time (5 minutes from now)
        $expiresAt = now()->addMinutes(5);

        // Delete any existing OTPs for this email
        PasswordResetOTP::where('email', $request->email)->delete();

        // Create new OTP record
        PasswordResetOTP::create([
            'email' => $request->email,
            'otp' => $otp,
            'expires_at' => $expiresAt,
            'used' => false,
        ]);

        // Send email with new OTP
        try {
            Mail::to($request->email)->send(new ForgotPasswordOTP($otp, $user->first_name));
            
            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'New OTP sent successfully to your email address',
                'data' => [
                    'email' => $request->email,
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s')
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to send OTP. Please try again later.'
            ], 200);
        }
    }
}
