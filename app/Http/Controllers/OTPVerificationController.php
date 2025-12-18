<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OTPVerificationController extends Controller
{
    /**
     * Show OTP verification form
     */
    public function show(Request $request)
    {
        // Get email from query parameter, session, or request
        $email = $request->query('email') ?? session('email') ?? $request->input('email');
        
        if ($email) {
            session(['email' => $email]);
        }
        
        return view('auth.verify-otp', ['email' => $email]);
    }

    /**
     * Verify OTP
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return back()->withErrors(['email' => 'User not found.']);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('login')
                ->with('success', 'Your email is already verified. You can now login.');
        }

        if (!$user->verifyOTP($validated['otp'])) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP. Please request a new one.'])->withInput();
        }

        // Mark email as verified
        $user->markEmailAsVerified();
        $user->clearOTP();

        // Clear the stored email from session
        session()->forget('email');

        return redirect()->route('login')
            ->with('success', 'Email verified successfully! You can now login.')
            ->with('clear_verification_email', true); // Flag to clear localStorage
    }

    /**
     * Resend OTP
     */
    public function resend(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('login')
                ->with('info', 'Your email is already verified.');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('success', 'A new OTP has been sent to your email address.');
    }
}

