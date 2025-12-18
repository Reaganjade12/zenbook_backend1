<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'email_verification_otp',
        'email_verification_otp_expires_at',
        'profile_image',
        'phone',
        'bio',
        'address',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_otp_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user is a customer
     */
    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * Check if user is a cleaner/therapist
     */
    public function isCleaner(): bool
    {
        return $this->role === 'cleaner' || $this->role === 'therapist';
    }

    /**
     * Check if user is a therapist
     */
    public function isTherapist(): bool
    {
        return $this->role === 'cleaner' || $this->role === 'therapist';
    }

    /**
     * Check if user is staff
     */
    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if user is admin (staff or super_admin)
     */
    public function isAdmin(): bool
    {
        return $this->role === 'staff' || $this->role === 'super_admin';
    }

    /**
     * Get the therapist profile
     */
    public function therapist()
    {
        return $this->hasOne(MassageTherapist::class);
    }

    /**
     * Get the cleaner profile (alias for backward compatibility)
     */
    public function cleaner()
    {
        return $this->therapist();
    }

    /**
     * Get bookings as customer
     */
    public function customerBookings()
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    /**
     * Get bookings as therapist
     */
    public function therapistBookings()
    {
        return $this->hasMany(Booking::class, 'therapist_id');
    }

    /**
     * Get bookings as cleaner (alias for backward compatibility)
     */
    public function cleanerBookings()
    {
        return $this->therapistBookings();
    }

    /**
     * Generate and send OTP for email verification
     */
    public function generateEmailVerificationOTP()
    {
        $otp = str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $this->update([
            'email_verification_otp' => Hash::make($otp),
            'email_verification_otp_expires_at' => now()->addMinutes(10),
        ]);
        
        return $otp;
    }

    /**
     * Verify OTP
     */
    public function verifyOTP($otp): bool
    {
        if (!$this->email_verification_otp || !$this->email_verification_otp_expires_at) {
            return false;
        }
        
        if ($this->email_verification_otp_expires_at->isPast()) {
            return false;
        }
        
        return Hash::check($otp, $this->email_verification_otp);
    }

    /**
     * Clear OTP after verification
     */
    public function clearOTP()
    {
        $this->update([
            'email_verification_otp' => null,
            'email_verification_otp_expires_at' => null,
        ]);
    }

    /**
     * Send the email verification notification with OTP.
     */
    public function sendEmailVerificationNotification()
    {
        $otp = $this->generateEmailVerificationOTP();
        $this->notify(new \App\Notifications\VerifyEmailOTP($otp));
    }

    /**
     * Get the profile image URL
     */
    public function getProfileImageUrlAttribute()
    {
        if ($this->profile_image) {
            // Use API route to bypass Apache direct file serving issues
            // This ensures CORS headers are always applied
            $baseUrl = config('app.url');
            if (config('app.env') === 'production' || strpos($baseUrl, 'apilaravel.bytevortexz.com') !== false) {
                $baseUrl = 'https://apilaravel.bytevortexz.com';
            }
            
            // Use API route instead of web route for better CORS handling
            $url = rtrim($baseUrl, '/') . '/api/storage/' . ltrim($this->profile_image, '/');
            
            // Force HTTPS for production to avoid mixed content issues
            $url = str_replace('http://', 'https://', $url);
            
            return $url;
        }
        return null; // Return null so frontend can handle default avatar
    }
}
