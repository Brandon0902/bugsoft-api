<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Super\UpdateSuperClinicUserRequest;
use App\Models\Clinic;
use App\Models\DentistProfile;
use App\Models\User;
use App\Services\UserCreationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SuperClinicUserController extends Controller
{
    use ApiResponse;

    public function index(Clinic $clinic): JsonResponse
    {
        $users = User::query()
            ->where('clinic_id', $clinic->id)
            ->whereIn('role', ['admin', 'receptionist', 'dentist'])
            ->with('dentistProfile')
            ->latest()
            ->get();

        return $this->successResponse($users, 'Usuarios de clínica listados.');
    }

    public function store(Clinic $clinic, StoreAdminUserRequest $request, UserCreationService $userCreationService): JsonResponse
    {
        $newUser = $userCreationService->createClinicStaff($clinic->id, $request->validated());

        return $this->successResponse($newUser, 'Usuario creado en clínica.', 201);
    }

    public function show(Clinic $clinic, User $user): JsonResponse
    {
        $user = $this->findClinicUser($clinic->id, $user);

        return $this->successResponse($user->load('dentistProfile'), 'Usuario encontrado.');
    }

    public function update(Clinic $clinic, User $user, UpdateSuperClinicUserRequest $request): JsonResponse
    {
        $user = $this->findClinicUser($clinic->id, $user);
        $data = $request->validated();
        unset($data['clinic_id']);
        $dentistProfileData = $data['dentist_profile'] ?? null;
        unset($data['dentist_profile']);

        DB::transaction(function () use ($clinic, $user, $data, $dentistProfileData): void {
            $oldRole = $user->role;
            $user->fill($data);
            $user->save();

            if ($oldRole !== $user->role) {
                if ($user->role === 'dentist') {
                    DentistProfile::query()->firstOrCreate([
                        'user_id' => $user->id,
                    ], [
                        'clinic_id' => $clinic->id,
                    ]);
                }

                if ($oldRole === 'dentist' && $user->role !== 'dentist') {
                    $user->dentistProfile()?->delete();
                }
            }

            if ($user->role === 'dentist' && is_array($dentistProfileData)) {
                $profileData = array_filter([
                    'clinic_id' => $clinic->id,
                    'specialty' => $dentistProfileData['specialty'] ?? null,
                    'license_number' => $dentistProfileData['license_number'] ?? null,
                    'color' => $dentistProfileData['color'] ?? null,
                ], static fn (mixed $value): bool => $value !== null);

                DentistProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData,
                );
            }
        });

        return $this->successResponse($user->fresh()->load('dentistProfile'), 'Usuario actualizado.');
    }

    public function destroy(Clinic $clinic, User $user): JsonResponse
    {
        $user = $this->findClinicUser($clinic->id, $user);
        $user->dentistProfile()?->delete();
        $user->delete();

        return $this->successResponse([], 'Usuario eliminado.');
    }

    private function findClinicUser(int $clinicId, User $user): User
    {
        return User::query()
            ->where('id', $user->id)
            ->where('clinic_id', $clinicId)
            ->whereIn('role', ['admin', 'dentist', 'receptionist'])
            ->firstOrFail();
    }
}
