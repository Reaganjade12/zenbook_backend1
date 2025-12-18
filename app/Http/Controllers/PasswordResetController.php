<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    /**
     * Show forgot password form
     */
    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send password reset link
     */
    public function sendResetLink(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

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

        // Send email
        $user->notify(new \App\Notifications\ResetPassword($token));

        return back()->with('success', 'Password reset link has been sent to your email address.');
    }

    /**
     * Show reset password form
     */
    public function showResetForm(Request $request, $token)
    {
        $email = $request->query('email');
        
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Reset password
     */
    public function reset(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        // Check if token exists and is valid
        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$passwordReset) {
            return back()->withErrors(['email' => 'Invalid reset token.'])->withInput();
        }

        // Check if token matches
        if (!Hash::check($validated['token'], $passwordReset->token)) {
            return back()->withErrors(['token' => 'Invalid or expired reset token.'])->withInput();
        }

        // Check if token is expired (60 minutes)
        if (Carbon::parse($passwordReset->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            return back()->withErrors(['token' => 'This password reset link has expired. Please request a new one.'])->withInput();
        }

        // Update password
        $user = User::where('email', $validated['email'])->first();
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Delete token
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return redirect()->route('login')
            ->with('success', 'Password reset successfully! You can now login with your new password.');
    }
}

