<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;
use App\Models\MassageTherapist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    /**
     * Show staff dashboard
     */
    public function dashboard(Request $request)
    {
        $status = $request->get('status');
        
        // Get all bookings
        $bookingsQuery = Booking::with(['customer', 'therapist'])->orderBy('created_at', 'desc');
        
        if ($status && in_array($status, ['pending', 'approved', 'declined', 'in_progress', 'completed'])) {
            $bookingsQuery->where('status', $status);
        }
        
        $bookings = $bookingsQuery->get();
        
        // Get statistics
        $stats = [
            'total_users' => User::where('role', 'customer')->count(),
            'total_therapists' => User::where('role', 'cleaner')->count(),
            'total_cleaners' => User::where('role', 'cleaner')->count(), // Keep for backward compatibility
            'total_bookings' => Booking::count(),
            'pending_bookings' => Booking::where('status', 'pending')->count(),
            'approved_bookings' => Booking::where('status', 'approved')->count(),
            'completed_bookings' => Booking::where('status', 'completed')->count(),
        ];

        // Get all users and therapists
        $users = User::where('role', 'customer')->orderBy('created_at', 'desc')->get();
        $therapists = User::where('role', 'cleaner')->with('therapist')->orderBy('created_at', 'desc')->get();
        $cleaners = $therapists; // Keep for backward compatibility

        return view('staff.dashboard', compact('bookings', 'users', 'therapists', 'cleaners', 'stats', 'status'));
    }

    /**
     * Delete a user
     */
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        if ($user->isStaff()) {
            return back()->with('error', 'Cannot delete staff user.');
        }
        
        $user->delete();
        
        return back()->with('success', 'User deleted successfully.');
    }

    /**
     * Delete a therapist/cleaner
     */
    public function deleteCleaner($id)
    {
        $user = User::where('id', $id)->where('role', 'cleaner')->firstOrFail();
        $user->delete();
        
        return back()->with('success', 'Massage therapist deleted successfully.');
    }

    /**
     * Delete a booking
     */
    public function deleteBooking($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->delete();
        
        return back()->with('success', 'Booking deleted successfully.');
    }

    /**
     * Show all therapists
     */
    public function indexTherapists()
    {
        $therapists = User::where('role', 'cleaner')
            ->with('therapist')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('staff.therapists.index', compact('therapists'));
    }

    /**
     * Show create therapist form
     */
    public function createTherapist()
    {
        return view('staff.therapists.create');
    }

    /**
     * Store new therapist
     */
    public function storeTherapist(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'is_available' => ['boolean'],
        ]);

        // Create user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'cleaner',
            'email_verified_at' => now(), // Auto-verify for admin-created accounts
        ]);

        // Create therapist profile
        MassageTherapist::create([
            'user_id' => $user->id,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'is_available' => $request->has('is_available') ? true : false,
        ]);

        return redirect()->route('staff.therapists.index')
            ->with('success', 'Therapist created successfully!');
    }

    /**
     * Show edit therapist form
     */
    public function editTherapist($id)
    {
        $user = User::where('id', $id)->where('role', 'cleaner')->with('therapist')->firstOrFail();
        return view('staff.therapists.edit', compact('user'));
    }

    /**
     * Update therapist
     */
    public function updateTherapist(Request $request, $id)
    {
        $user = User::where('id', $id)->where('role', 'cleaner')->with('therapist')->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'is_available' => ['boolean'],
        ]);

        // Update user
        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);

        // Update or create therapist profile
        if ($user->therapist) {
            $user->therapist->update([
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'is_available' => $request->has('is_available') ? true : false,
            ]);
        } else {
            MassageTherapist::create([
                'user_id' => $user->id,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'is_available' => $request->has('is_available') ? true : false,
            ]);
        }

        return redirect()->route('staff.therapists.index')
            ->with('success', 'Therapist updated successfully!');
    }

    /**
     * Delete a therapist
     */
    public function deleteTherapist($id)
    {
        $user = User::where('id', $id)->where('role', 'cleaner')->firstOrFail();
        $user->delete();
        
        return back()->with('success', 'Massage therapist deleted successfully.');
    }
}

