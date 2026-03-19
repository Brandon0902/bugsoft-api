<?php

namespace App\Services;

use App\Models\DentistProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserCreationService
{
    /**
     * @param  array{
     *   name:string,
     *   email:string,
     *   password:string,
     *   role:string,
     *   phone?:?string,
     *   status?:?bool,
     *   dentist_profile?: array{
     *     specialty?: ?string,
     *     license_number?: ?string,
     *     color?: ?string
     *   },
     *   specialty_ids?: ?array<int>
     * }  $data
     */
    public function createClinicStaff(int $clinicId, array $data): User
    {
        return DB::transaction(function () use ($clinicId, $data) {
            $dentistProfileData = $data['dentist_profile'] ?? null;
            $specialtyIds = $data['specialty_ids'] ?? null;

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
                $profile = DentistProfile::query()->create([
                    'user_id' => $user->id,
                    'clinic_id' => $clinicId,
                    'specialty' => $dentistProfileData['specialty'] ?? null,
                    'license_number' => $dentistProfileData['license_number'] ?? null,
                    'color' => $dentistProfileData['color'] ?? null,
                ]);

                if (is_array($specialtyIds)) {
                    $profile->specialties()->sync($specialtyIds);
                }
            }

            return $user->load('dentistProfile.specialties');
        });
    }
}
