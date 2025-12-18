<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MassageTherapist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLogin()
    {
        $frontendUrl = config('app.frontend_url');

        if (!empty($frontendUrl)) {
            return redirect()->away(rtrim($frontendUrl, '/') . '/views/auth/login.html');
        }

        return view('auth.login');
    }

    /**
     * Handle login
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            
            $user = Auth::user();
            
            // Check if email is verified
            if (!$user->hasVerifiedEmail()) {
                Auth::logout();
                return redirect()->route('otp.verify')
                    ->with('email', $user->email)
                    ->withErrors([
                        'email' => 'Please verify your email address with the OTP code before logging in.',
                    ]);
            }
            
            // Redirect based on role
            if ($user->isStaff()) {
                return redirect()->route('staff.dashboard');
            } elseif ($user->isCleaner()) {
                return redirect()->route('therapist.dashboard');
            } else {
                return redirect()->route('customer.dashboard');
            }
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Show registration form
     */
    public function showRegister()
    {
        $frontendUrl = config('app.frontend_url');

        if (!empty($frontendUrl)) {
            return redirect()->away(rtrim($frontendUrl, '/') . '/views/auth/register.html');
        }

        return view('auth.register');
    }

    /**
     * Handle registration
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

        return redirect()->route('otp.verify')
            ->with('email', $user->email)
            ->with('success', 'Registration successful! Please check your email for the OTP code.');
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        // Get the authenticated user from session before logging out
        $user = Auth::user();
        
        // Revoke all API tokens from database before logging out
        if ($user) {
            $user->tokens()->delete();
        }
        
        // Logout and clear session
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

