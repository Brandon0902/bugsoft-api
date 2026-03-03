<?php

namespace App\Services;

use App\Models\DentistProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserCreationService
{
    /**
     * @param  array{name:string,email:string,password:string,role:string,phone?:?string,status?:?bool}  $data
     */
    public function createClinicStaff(int $clinicId, array $data): User
    {
        return DB::transaction(function () use ($clinicId, $data) {
            $user = User::query()->create([
                'clinic_id' => $clinicId,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'role' => $data['role'],
                'status' => $data['status'] ?? true,
            ]);

            if ($user->role === 'dentist') {
                DentistProfile::query()->create([
                    'user_id' => $user->id,
                    'clinic_id' => $clinicId,
                ]);
            }

            return $user->load('dentistProfile');
        });
    }
}

