<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MassageTherapist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle API login
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // For API authentication, allow login regardless of email verification
        // Staff/admin users can always login, regular users can login but may need verification for certain actions
        // Note: You can uncomment the verification check below if you want to enforce email verification
        
        // Optional: Check email verification for non-staff users
        // if (!$user->isStaff() && !$user->hasVerifiedEmail()) {
        //     return response()->json([
        //         'message' => 'Please verify your email address with the OTP code before logging in.',
        //         'requires_verification' => true,
        //         'email' => $user->email,
        //     ], 403);
        // }

        // Revoke all existing tokens (optional - for single device login)
        // $user->tokens()->delete();

        // Create token for all authenticated users
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Handle API registration
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Registration is for customers only - therapists are added by admin
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'customer', // Always set to customer for public registration
        ]);

        // Send email verification OTP
        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Registration successful! Please check your email for the OTP code.',
            'requires_verification' => true,
            'email' => $user->email,
        ], 201);
    }

    /**
     * Handle logout (revoke token)
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        
        // Delete all tokens from database (complete logout)
        // If you want to keep other device tokens active, use: $user->currentAccessToken()->delete();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'address' => $user->address,
                'profile_image' => $user->profile_image,
                'profile_image_url' => $user->profile_image_url,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
            ],
        ]);
    }

    /**
     * Verify OTP
     */
    public function verifyOTP(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Your email is already verified.',
                'verified' => true,
            ]);
        }

        if (!$user->verifyOTP($validated['otp'])) {
            return response()->json([
                'message' => 'Invalid or expired OTP. Please request a new one.',
            ], 422);
        }

        // Mark email as verified
        $user->markEmailAsVerified();
        $user->clearOTP();

        return response()->json([
            'message' => 'Email verified successfully! You can now login.',
            'verified' => true,
        ]);
    }

    /**
     * Resend OTP
     */
    public function resendOTP(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Your email is already verified.',
                'verified' => true,
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'A new OTP has been sent to your email address.',
        ]);
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        // Generate token
        $token = Str::random(64);
        
        // Delete old tokens
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
        
        // Insert new token
        DB::table('password_reset_tokens')->insert([
            'email' => $validated['email'],
            'token' => Hash::make($token),
            'created_at' => Carbon::now(),
        ]);

        // Send email with frontend URL
        $frontendUrl = config('app.frontend_url', 'https://reaganjade12.github.io/zenbook_frontend1');
        $resetUrl = rtrim($frontendUrl, '/') . '/views/auth/reset-password.html?token=' . $token . '&email=' . urlencode($validated['email']);

        try {
            // Send email notification
            $user->notify(new \App\Notifications\ResetPassword($token, $resetUrl));
            
            Log::info('Password reset email sent', [
                'email' => $validated['email'],
                'reset_url' => $resetUrl,
            ]);

            return response()->json([
                'message' => 'Password reset link has been sent to your email address.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            // Still return success to prevent email enumeration
            // The token is saved, so they can request again if email fails
            return response()->json([
                'message' => 'If an account exists with that email, a password reset link has been sent.',
            ]);
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Check if token exists and is valid
        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'message' => 'Invalid reset token.',
                'error' => 'Invalid token',
            ], 400);
        }

        // Check if token matches
        if (!Hash::check($validated['token'], $passwordReset->token)) {
            return response()->json([
                'message' => 'Invalid or expired reset token.',
                'error' => 'Invalid token',
            ], 400);
        }

        // Check if token is expired (60 minutes)
        if (Carbon::parse($passwordReset->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            return response()->json([
                'message' => 'This password reset link has expired. Please request a new one.',
                'error' => 'Expired token',
            ], 400);
        }

        // Update password
        $user = User::where('email', $validated['email'])->first();
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Delete token
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return response()->json([
            'message' => 'Password reset successfully! You can now login with your new password.',
        ]);
    }
}

