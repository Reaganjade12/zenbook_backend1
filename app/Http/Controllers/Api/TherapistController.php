<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MassageTherapist;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TherapistController extends Controller
{
    /**
     * Get therapist dashboard
     */
    public function dashboard(Request $request)
    {
        $status = $request->get('status');
        
        $therapist = MassageTherapist::where('user_id', Auth::id())->firstOrFail();
        
        $query = Booking::where('therapist_id', Auth::id())->orderBy('created_at', 'desc');
        
        if ($status && in_array($status, ['pending', 'approved', 'declined', 'in_progress', 'completed'])) {
            $query->where('status', $status);
        }
        
        $bookings = $query->with(['customer'])->get();

        return response()->json([
            'therapist' => $therapist,
            'is_available' => (bool) $therapist->is_available,
            'bookings' => $bookings,
            'status' => $status,
        ]);
    }

    /**
     * Get all bookings for therapist
     */
    public function bookings(Request $request)
    {
        $status = $request->get('status');
        
        $therapist = MassageTherapist::where('user_id', Auth::id())->firstOrFail();
        
        $query = Booking::where('therapist_id', Auth::id())->orderBy('created_at', 'desc');
        
        if ($status && in_array($status, ['pending', 'approved', 'declined', 'in_progress', 'completed'])) {
            $query->where('status', $status);
        }
        
        $bookings = $query->with(['customer'])->get();

        return response()->json([
            'bookings' => $bookings,
            'is_available' => (bool) $therapist->is_available,
        ]);
    }

    public function customers()
    {
        $therapistId = Auth::id();

        $customerIds = Booking::where('therapist_id', $therapistId)
            ->distinct()
            ->pluck('customer_id');

        $customers = User::whereIn('id', $customerIds)
            ->withCount([
                'customerBookings as customer_bookings_count' => function ($query) use ($therapistId) {
                    $query->where('therapist_id', $therapistId);
                },
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'created_at']);

        return response()->json([
            'customers' => $customers,
        ]);
    }

    /**
     * Accept a booking
     */
    public function acceptBooking($id)
    {
        $booking = Booking::where('therapist_id', Auth::id())
            ->find($id);

        if (!$booking) {
            return response()->json([
                'message' => 'Booking not found or you do not have permission to access it.',
                'error' => 'Booking not found',
            ], 404);
        }

        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending bookings can be accepted. Current status: ' . $booking->status,
                'error' => 'Invalid booking status',
                'current_status' => $booking->status,
            ], 400);
        }

        $booking->update(['status' => 'approved']);

        return response()->json([
            'message' => 'Booking accepted successfully!',
            'booking' => $booking->load(['customer']),
        ]);
    }

    /**
     * Decline a booking
     */
    public function declineBooking($id)
    {
        $booking = Booking::where('therapist_id', Auth::id())
            ->find($id);

        if (!$booking) {
            return response()->json([
                'message' => 'Booking not found or you do not have permission to access it.',
                'error' => 'Booking not found',
            ], 404);
        }

        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending bookings can be declined. Current status: ' . $booking->status,
                'error' => 'Invalid booking status',
                'current_status' => $booking->status,
            ], 400);
        }

        $booking->update(['status' => 'declined']);

        return response()->json([
            'message' => 'Booking declined.',
            'booking' => $booking->load(['customer']),
        ]);
    }

    /**
     * Update booking status
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:in_progress,completed'],
        ]);

        $booking = Booking::where('therapist_id', Auth::id())
            ->find($id);

        if (!$booking) {
            return response()->json([
                'message' => 'Booking not found or you do not have permission to access it.',
                'error' => 'Booking not found',
            ], 404);
        }

        // Allow updating from 'approved' or 'in_progress' to the next status
        if ($validated['status'] === 'in_progress' && $booking->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved bookings can be set to in progress. Current status: ' . $booking->status,
                'error' => 'Invalid booking status',
                'current_status' => $booking->status,
            ], 400);
        }

        if ($validated['status'] === 'completed' && !in_array($booking->status, ['approved', 'in_progress'])) {
            return response()->json([
                'message' => 'Only approved or in-progress bookings can be completed. Current status: ' . $booking->status,
                'error' => 'Invalid booking status',
                'current_status' => $booking->status,
            ], 400);
        }

        $booking->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Booking status updated successfully!',
            'booking' => $booking->load(['customer']),
        ]);
    }

    /**
     * Toggle therapist availability
     */
    public function toggleAvailability(Request $request)
    {
        $therapist = MassageTherapist::where('user_id', Auth::id())->firstOrFail();
        
        $therapist->update([
            'is_available' => !$therapist->is_available,
        ]);

        return response()->json([
            'message' => 'Availability updated successfully!',
            'is_available' => $therapist->is_available,
        ]);
    }
}

