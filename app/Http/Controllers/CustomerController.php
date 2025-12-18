<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\MassageTherapist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    /**
     * Show customer dashboard
     */
    public function dashboard(Request $request)
    {
        $status = $request->get('status');
        
        $query = Auth::user()->customerBookings()->orderBy('created_at', 'desc');
        
        if ($status && in_array($status, ['pending', 'approved', 'declined', 'in_progress', 'completed'])) {
            $query->where('status', $status);
        }
        
        $bookings = $query->get();

        return view('customer.dashboard', compact('bookings', 'status'));
    }

    /**
     * Show booking form
     */
    public function showBookingForm()
    {
        // Get all available therapists
        $availableTherapists = \App\Models\MassageTherapist::where('is_available', true)
            ->with('user')
            ->get()
            ->map(function($therapist) {
                return [
                    'id' => $therapist->user_id,
                    'name' => $therapist->user->name,
                    'bio' => $therapist->bio,
                ];
            });
        
        return view('customer.create-booking', compact('availableTherapists'));
    }

    /**
     * Create a new booking
     */
    public function createBooking(Request $request)
    {
        $validated = $request->validate([
            'therapist_id' => ['required', 'exists:users,id'],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'booking_time' => ['required'],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'service_type' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'therapist_id.required' => 'Please select a therapist.',
            'therapist_id.exists' => 'The selected therapist is not available.',
            'booking_date.after_or_equal' => 'The booking date cannot be in the past. Please select today or a future date.',
        ]);

        // Verify therapist is available
        $therapist = MassageTherapist::where('user_id', $validated['therapist_id'])
            ->where('is_available', true)
            ->firstOrFail();

        Booking::create([
            'customer_id' => Auth::id(),
            'therapist_id' => $validated['therapist_id'],
            'booking_date' => $validated['booking_date'],
            'booking_time' => $validated['booking_time'],
            'address' => $validated['address'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'service_type' => $validated['service_type'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()->route('customer.dashboard')
            ->with('success', 'Booking request created successfully!');
    }

    /**
     * View booking history
     */
    public function bookingHistory(Request $request)
    {
        $status = $request->get('status');
        
        $query = Auth::user()->customerBookings()->orderBy('created_at', 'desc');
        
        if ($status && in_array($status, ['pending', 'approved', 'declined', 'in_progress', 'completed'])) {
            $query->where('status', $status);
        }
        
        $bookings = $query->get();

        return view('customer.history', compact('bookings', 'status'));
    }
}

