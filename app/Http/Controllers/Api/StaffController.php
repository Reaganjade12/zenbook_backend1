<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Models\MassageTherapist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    /**
     * Get staff dashboard
     */
    public function dashboard()
    {
        $totalUsers = User::where('role', 'customer')->count();
        $totalTherapists = User::whereIn('role', ['cleaner', 'therapist'])->count();
        $totalBookings = Booking::count();
        $pendingBookings = Booking::where('status', 'pending')->count();
        $approvedBookings = Booking::where('status', 'approved')->count();
        $inProgressBookings = Booking::where('status', 'in_progress')->count();
        $completedBookings = Booking::where('status', 'completed')->count();
        $declinedBookings = Booking::where('status', 'declined')->count();

        return response()->json([
            'stats' => [
                'total_users' => $totalUsers,
                'total_therapists' => $totalTherapists,
                'total_cleaners' => $totalTherapists,
                'total_bookings' => $totalBookings,
                'pending_bookings' => $pendingBookings,
                'approved_bookings' => $approvedBookings,
                'in_progress_bookings' => $inProgressBookings,
                'completed_bookings' => $completedBookings,
                'declined_bookings' => $declinedBookings,
            ],
        ]);
    }

    /**
     * Get all users
     */
    public function users()
    {
        $users = User::where('role', 'customer')->get();

        return response()->json([
            'users' => $users,
        ]);
    }

    /**
     * Get all therapists
     */
    public function therapists()
    {
        $users = User::whereIn('role', ['cleaner', 'therapist'])->get();
        
        // Manually load therapist data to avoid relationship loading issues
        $therapists = $users->map(function ($user) {
            $therapistProfile = MassageTherapist::where('user_id', $user->id)->first();
            
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'profile_image' => $user->profile_image,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'address' => $user->address,
                'therapist' => $therapistProfile ? [
                    'id' => $therapistProfile->id,
                    'user_id' => $therapistProfile->user_id,
                    'phone' => $therapistProfile->phone,
                    'address' => $therapistProfile->address,
                    'bio' => $therapistProfile->bio,
                    'is_available' => $therapistProfile->is_available,
                ] : null,
            ];
        });

        return response()->json([
            'therapists' => $therapists,
        ]);
    }

    /**
     * Get single therapist
     */
    public function therapist($id)
    {
        $user = User::whereIn('role', ['cleaner', 'therapist'])->findOrFail($id);
        $therapistProfile = MassageTherapist::where('user_id', $user->id)->first();
        
        $therapist = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'profile_image' => $user->profile_image,
            'phone' => $user->phone,
            'bio' => $user->bio,
            'address' => $user->address,
            'therapist' => $therapistProfile ? [
                'id' => $therapistProfile->id,
                'user_id' => $therapistProfile->user_id,
                'phone' => $therapistProfile->phone,
                'address' => $therapistProfile->address,
                'bio' => $therapistProfile->bio,
                'is_available' => $therapistProfile->is_available,
            ] : null,
        ];

        return response()->json([
            'therapist' => $therapist,
        ]);
    }

    /**
     * Create therapist
     */
    public function createTherapist(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'is_available' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'cleaner',
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'bio' => $validated['bio'] ?? null,
        ]);

        MassageTherapist::create([
            'user_id' => $user->id,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'is_available' => (bool)($validated['is_available'] ?? false),
        ]);

        $therapistProfile = MassageTherapist::where('user_id', $user->id)->first();
        
        return response()->json([
            'message' => 'Therapist created successfully!',
            'therapist' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'profile_image' => $user->profile_image,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'address' => $user->address,
                'therapist' => $therapistProfile ? [
                    'id' => $therapistProfile->id,
                    'user_id' => $therapistProfile->user_id,
                    'phone' => $therapistProfile->phone,
                    'address' => $therapistProfile->address,
                    'bio' => $therapistProfile->bio,
                    'is_available' => $therapistProfile->is_available,
                ] : null,
            ],
        ], 201);
    }

    /**
     * Update therapist
     */
    public function updateTherapist(Request $request, $id)
    {
        $user = User::whereIn('role', ['cleaner', 'therapist'])->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'is_available' => ['nullable', 'boolean'],
        ]);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'bio' => $validated['bio'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);

        $profileData = [
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'is_available' => (bool)($validated['is_available'] ?? false),
        ];

        $therapistProfile = MassageTherapist::where('user_id', $user->id)->first();
        
        if ($therapistProfile) {
            $therapistProfile->update($profileData);
        } else {
            MassageTherapist::create(array_merge($profileData, ['user_id' => $user->id]));
        }

        $therapistProfile = MassageTherapist::where('user_id', $user->id)->first();
        
        return response()->json([
            'message' => 'Therapist updated successfully!',
            'therapist' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'profile_image' => $user->profile_image,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'address' => $user->address,
                'therapist' => $therapistProfile ? [
                    'id' => $therapistProfile->id,
                    'user_id' => $therapistProfile->user_id,
                    'phone' => $therapistProfile->phone,
                    'address' => $therapistProfile->address,
                    'bio' => $therapistProfile->bio,
                    'is_available' => $therapistProfile->is_available,
                ] : null,
            ],
        ]);
    }

    /**
     * Get all bookings
     */
    public function bookings(Request $request)
    {
        try {
            $status = $request->get('status');
            
            $query = Booking::query()->orderBy('created_at', 'desc');
            
            if ($status && in_array($status, ['pending', 'approved', 'declined', 'in_progress', 'completed'])) {
                $query->where('status', $status);
            }
            
            $bookings = $query->get();
            
            // Use DB queries to completely avoid Eloquent relationship loading
            $formattedBookings = $bookings->map(function ($booking) {
                // Get customer data using DB query to avoid any Eloquent relationship loading
                $customerData = null;
                if ($booking->customer_id) {
                    $customer = DB::table('users')
                        ->select('id', 'name', 'email', 'profile_image', 'role')
                        ->where('id', $booking->customer_id)
                        ->first();
                    if ($customer) {
                        $customerData = (array) $customer;
                    }
                }
                
                // Get therapist data using DB query to avoid any Eloquent relationship loading
                $therapistData = null;
                if ($booking->therapist_id) {
                    $therapist = DB::table('users')
                        ->select('id', 'name', 'email', 'profile_image', 'role')
                        ->where('id', $booking->therapist_id)
                        ->first();
                    if ($therapist) {
                        $therapistData = (array) $therapist;
                    }
                }
                
                return [
                    'id' => $booking->id,
                    'customer_id' => $booking->customer_id,
                    'therapist_id' => $booking->therapist_id,
                    'booking_date' => $booking->booking_date ? $booking->booking_date->format('Y-m-d') : null,
                    'booking_time' => $booking->booking_time ? $booking->booking_time->format('Y-m-d H:i:s') : null,
                    'address' => $booking->address,
                    'latitude' => $booking->latitude,
                    'longitude' => $booking->longitude,
                    'service_type' => $booking->service_type,
                    'notes' => $booking->notes,
                    'status' => $booking->status,
                    'created_at' => $booking->created_at ? $booking->created_at->toISOString() : null,
                    'updated_at' => $booking->updated_at ? $booking->updated_at->toISOString() : null,
                    'customer' => $customerData,
                    'therapist' => $therapistData,
                ];
            });

            return response()->json([
                'bookings' => $formattedBookings,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading bookings: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'An error occurred while loading bookings.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete a user
     */
    public function deleteUser($id)
    {
        $user = User::where('role', 'customer')->findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully!',
        ]);
    }

    /**
     * Delete a therapist
     */
    public function deleteTherapist($id)
    {
        $user = User::whereIn('role', ['cleaner', 'therapist'])->findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'Therapist deleted successfully!',
        ]);
    }

    /**
     * Delete a booking
     */
    public function deleteBooking($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->delete();

        return response()->json([
            'message' => 'Booking deleted successfully!',
        ]);
    }

    /**
     * Get all admins (super admin only)
     */
    public function getAdmins()
    {
        $user = Auth::user();
        
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Only super admins can access this resource.',
            ], 403);
        }

        $admins = User::where('role', 'staff')->get();

        return response()->json([
            'admins' => $admins,
        ]);
    }

    /**
     * Create admin (super admin only)
     */
    public function createAdmin(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Only super admins can create admins.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $admin = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'Admin created successfully!',
            'admin' => $admin,
        ], 201);
    }

    /**
     * Update admin (super admin only)
     */
    public function updateAdmin(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Only super admins can update admins.',
            ], 403);
        }

        $admin = User::where('role', 'staff')->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $adminData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (!empty($validated['password'])) {
            $adminData['password'] = Hash::make($validated['password']);
        }

        $admin->update($adminData);

        return response()->json([
            'message' => 'Admin updated successfully!',
            'admin' => $admin,
        ]);
    }

    /**
     * Delete admin (super admin only)
     */
    public function deleteAdmin($id)
    {
        $user = Auth::user();
        
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Only super admins can delete admins.',
            ], 403);
        }

        $admin = User::where('role', 'staff')->findOrFail($id);
        
        // Prevent deleting yourself
        if ($admin->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 403);
        }

        $admin->delete();

        return response()->json([
            'message' => 'Admin deleted successfully!',
        ]);
    }

    /**
     * Create user (admin only)
     */
    public function createUser(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Only admins can create users.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'customer',
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'User created successfully!',
            'user' => $newUser,
        ], 201);
    }

    /**
     * Update user (admin only)
     */
    public function updateUser(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Only admins can update users.',
            ], 403);
        }

        $targetUser = User::where('role', 'customer')->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($targetUser->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $targetUser->update($userData);

        return response()->json([
            'message' => 'User updated successfully!',
            'user' => $targetUser,
        ]);
    }
}

