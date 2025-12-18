<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\MassageTherapist;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CleanerController extends Controller
{
    /**
     * Show cleaner dashboard
     */
    public function dashboard(Request $request)
    {
        $status = $request->get('status');
        
        $query = Booking::with(['customer', 'therapist']);
        
        if ($status && in_array($status, ['pending', 'approved', 'declined', 'in_progress', 'completed'])) {
            // Show bookings assigned to this therapist with the specified status
            $query->where('status', $status)
                  ->where('therapist_id', Auth::id());
        } else {
            // Show all bookings assigned to this therapist (any status)
            $query->where('therapist_id', Auth::id());
        }
        
        $bookings = $query->orderBy('created_at', 'desc')->get();

        // Get therapist availability status
        $therapist = MassageTherapist::where('user_id', Auth::id())->first();
        $isAvailable = $therapist ? $therapist->is_available : false;

        return view('therapist.dashboard', compact('bookings', 'status', 'isAvailable'));
    }

    /**
     * Accept a booking
     */
    public function acceptBooking(Request $request, $id)
    {
        $booking = Booking::where('id', $id)
            ->where('therapist_id', Auth::id())
            ->firstOrFail();
        
        // Only allow accepting pending bookings
        if ($booking->status !== 'pending') {
            return back()->with('error', 'This booking cannot be accepted.');
        }

        $booking->update([
            'status' => 'approved',
        ]);

        return back()->with('success', 'Booking accepted successfully!');
    }

    /**
     * Decline a booking
     */
    public function declineBooking(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);
        
        // Only allow declining pending or approved bookings
        if (!in_array($booking->status, ['pending', 'approved'])) {
            return back()->with('error', 'This booking cannot be declined.');
        }

        $booking->update([
            'status' => 'declined',
        ]);

        return back()->with('success', 'Booking declined.');
    }

    /**
     * Update booking status (In Progress, Completed)
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:in_progress,completed'],
        ]);

        $booking = Booking::where('id', $id)
            ->where('therapist_id', Auth::id())
            ->firstOrFail();

        $booking->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Booking status updated successfully!');
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

        $status = $therapist->is_available ? 'available' : 'unavailable';
        
        return back()->with('success', "You are now {$status} for new bookings.");
    }

    /**
     * Show all bookings for therapist
     */
    public function bookings(Request $request)
    {
        $status = $request->get('status');
        
        $query = Booking::where('therapist_id', Auth::id())
            ->with(['customer', 'therapist'])
            ->orderBy('created_at', 'desc');
        
        if ($status && in_array($status, ['pending', 'approved', 'declined', 'in_progress', 'completed'])) {
            $query->where('status', $status);
        }
        
        $bookings = $query->get();

        // Get therapist availability status
        $therapist = MassageTherapist::where('user_id', Auth::id())->first();
        $isAvailable = $therapist ? $therapist->is_available : false;

        return view('therapist.bookings', compact('bookings', 'status', 'isAvailable'));
    }

    /**
     * Show all customers who have booked with this therapist
     */
    public function customers()
    {
        // Get unique customers who have bookings with this therapist
        $customerIds = Booking::where('therapist_id', Auth::id())
            ->distinct()
            ->pluck('customer_id');
        
        $customers = User::whereIn('id', $customerIds)
            ->where('role', 'customer')
            ->withCount(['customerBookings' => function($query) {
                $query->where('therapist_id', Auth::id());
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('therapist.customers', compact('customers'));
    }
}

