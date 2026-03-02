<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientProfile extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'clinic_id', 'birth_date', 'gender', 'address', 'allergies', 'notes'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }
}
