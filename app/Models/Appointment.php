<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    public const STATUSES = ['scheduled', 'confirmed', 'completed', 'canceled', 'no_show'];

    protected $fillable = [
        'clinic_id',
        'patient_user_id',
        'dentist_user_id',
        'created_by',
        'start_at',
        'end_at',
        'status',
        'reason',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dentist_user_id');
    }
}
