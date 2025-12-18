<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MassageTherapist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    /**
     * Get customer dashboard data
     */
    public function dashboard(Request $request)
    {
        $status = $request->get('status');
        
        $query = Auth::user()->customerBookings()->orderBy('created_at', 'desc');
        
        if ($status && in_array($status, ['pending', 'approved', 'declined', 'in_progress', 'completed'])) {
            $query->where('status', $status);
        }
        
        $bookings = $query->with(['therapist'])->get();

        return response()->json([
            'bookings' => $bookings,
            'status' => $status,
        ]);
    }

    /**
     * Get available therapists
     */
    public function getAvailableTherapists()
    {
        $therapists = MassageTherapist::where('is_available', true)
            ->with('user')
            ->get()
            ->map(function($therapist) {
                return [
                    'id' => $therapist->user_id,
                    'name' => $therapist->user->name,
                    'bio' => $therapist->bio,
                ];
            });

        return response()->json([
            'therapists' => $therapists,
        ]);
    }

    /**
     * Get all bookings for customer
     */
    public function index(Request $request)
    {
        $status = $request->get('status');
        
        $query = Auth::user()->customerBookings()->orderBy('created_at', 'desc');
        
        if ($status && in_array($status, ['pending', 'approved', 'declined', 'in_progress', 'completed'])) {
            $query->where('status', $status);
        }
        
        $bookings = $query->with(['therapist'])->get();

        return response()->json([
            'bookings' => $bookings,
        ]);
    }

    /**
     * Create a new booking
     */
    public function store(Request $request)
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
            'booking_date.after_or_equal' => 'The booking date cannot be in the past.',
        ]);

        // Verify therapist is available
        $therapist = MassageTherapist::where('user_id', $validated['therapist_id'])
            ->where('is_available', true)
            ->firstOrFail();

        $booking = Booking::create([
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

        return response()->json([
            'message' => 'Booking request created successfully!',
            'booking' => $booking->load(['therapist']),
        ], 201);
    }

    /**
     * Get a specific booking
     */
    public function show($id)
    {
        $booking = Auth::user()->customerBookings()->with(['therapist'])->findOrFail($id);

        return response()->json([
            'booking' => $booking,
        ]);
    }

    /**
     * Update a booking
     */
    public function update(Request $request, $id)
    {
        $booking = Auth::user()->customerBookings()->findOrFail($id);

        // Only allow updates if booking is pending
        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending bookings can be updated.',
            ], 403);
        }

        $validated = $request->validate([
            'booking_date' => ['sometimes', 'date', 'after_or_equal:today'],
            'booking_time' => ['sometimes'],
            'address' => ['sometimes', 'string', 'max:500'],
            'service_type' => ['sometimes', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $booking->update($validated);

        return response()->json([
            'message' => 'Booking updated successfully!',
            'booking' => $booking->load(['therapist']),
        ]);
    }

    /**
     * Delete a booking
     */
    public function destroy($id)
    {
        $booking = Auth::user()->customerBookings()->findOrFail($id);

        // Only allow deletion if booking is pending
        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending bookings can be deleted.',
            ], 403);
        }

        $booking->delete();

        return response()->json([
            'message' => 'Booking deleted successfully!',
        ]);
    }
}

