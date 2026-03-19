<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DentistProfile extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'clinic_id', 'specialty', 'license_number', 'color'];

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'dentist_specialty')
            ->withTimestamps();
    }
}
