<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MassageTherapist extends Model
{
    use HasFactory;

    protected $table = 'massage_therapists';

    protected $fillable = [
        'user_id',
        'phone',
        'address',
        'bio',
        'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    /**
     * Get the user that owns the therapist profile
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

