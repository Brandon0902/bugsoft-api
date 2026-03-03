<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLES = ['super_admin', 'admin', 'receptionist', 'dentist', 'pacient'];

    protected $fillable = [
        'clinic_id',
        'name',
        'email',
        'password',
        'role',
        'status',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patientProfile(): HasOne
    {
        return $this->hasOne(PatientProfile::class);
    }

    public function dentistProfile(): HasOne
    {
        return $this->hasOne(DentistProfile::class);
    }
}