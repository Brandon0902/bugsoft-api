<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\DentistProfile;
use App\Models\User;
use App\Services\UserCreationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $user = request()->user();

        $users = User::query()
            ->where('clinic_id', $user->clinic_id)
            ->whereIn('role', ['dentist', 'receptionist'])
            ->where('id', '!=', $user->id)
            ->with(['dentistProfile', 'dentistProfile.specialties'])
            ->latest()
            ->get();

        return $this->successResponse($users, 'Usuarios de clínica listados.');
    }

    public function store(StoreAdminUserRequest $request, UserCreationService $userCreationService): JsonResponse
    {
        $authUser = request()->user();
        $data = $request->validated();

        $newUser = $userCreationService->createClinicStaff((int) $authUser->clinic_id, $data);

        return $this->successResponse($newUser, 'Usuario creado en clínica.', 201);
    }

    public function show(User $user): JsonResponse
    {
        $user = $this->findClinicStaff($user);

        return $this->successResponse($user->load(['dentistProfile', 'dentistProfile.specialties']), 'Usuario encontrado.');
    }

    public function update(User $user, UpdateAdminUserRequest $request): JsonResponse
    {
        $user = $this->findClinicStaff($user);
        $data = $request->validated();
        unset($data['clinic_id']);
        $dentistProfileData = $data['dentist_profile'] ?? null;
        $specialtyIds = $data['specialty_ids'] ?? null;
        unset($data['dentist_profile'], $data['specialty_ids']);

        DB::transaction(function () use ($user, $data, $dentistProfileData, $specialtyIds): void {
            $oldRole = $user->role;
            $user->fill($data);
            $user->save();

            if ($oldRole !== $user->role) {
                if ($user->role === 'dentist') {
                    DentistProfile::query()->firstOrCreate([
                        'user_id' => $user->id,
                    ], [
                        'clinic_id' => $user->clinic_id,
                    ]);
                }

                if ($oldRole === 'dentist' && $user->role === 'receptionist') {
                    $user->dentistProfile()?->delete();
                }
            }

            if ($user->role === 'dentist' && is_array($dentistProfileData)) {
                $profileData = array_filter([
                    'clinic_id' => $user->clinic_id,
                    'specialty' => $dentistProfileData['specialty'] ?? null,
                    'license_number' => $dentistProfileData['license_number'] ?? null,
                    'color' => $dentistProfileData['color'] ?? null,
                ], static fn (mixed $value): bool => $value !== null);

                DentistProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData,
                );
            }

            if ($user->role === 'dentist' && is_array($specialtyIds)) {
                $profile = DentistProfile::query()->firstOrCreate(
                    ['user_id' => $user->id],
                    ['clinic_id' => $user->clinic_id],
                );

                $profile->specialties()->sync($specialtyIds);
            }
        });

        return $this->successResponse($user->fresh()->load(['dentistProfile', 'dentistProfile.specialties']), 'Usuario actualizado.');
    }

    public function destroy(User $user): JsonResponse
    {
        $user = $this->findClinicStaff($user);
        $user->dentistProfile()?->delete();
        $user->delete();

        return $this->successResponse([], 'Usuario eliminado.');
    }

    private function findClinicStaff(User $user): User
    {
        return User::query()
            ->where('id', $user->id)
            ->where('clinic_id', request()->user()->clinic_id)
            ->whereIn('role', ['dentist', 'receptionist'])
            ->firstOrFail();
    }
}
