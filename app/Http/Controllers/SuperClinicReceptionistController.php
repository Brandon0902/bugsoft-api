<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Receptionist\StoreReceptionistRequest;
use App\Http\Requests\Receptionist\UpdateReceptionistRequest;
use App\Models\Clinic;
use App\Models\User;
use App\Services\UserCreationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class SuperClinicReceptionistController extends Controller
{
    use ApiResponse;

    public function index(Clinic $clinic): JsonResponse
    {
        $users = User::query()
            ->where('clinic_id', $clinic->id)
            ->where('role', 'receptionist')
            ->latest()
            ->get();

        return $this->successResponse($users, 'Recepcionistas de clínica listados.');
    }

    public function store(Clinic $clinic, StoreReceptionistRequest $request, UserCreationService $userCreationService): JsonResponse
    {
        $data = $request->validated();
        $data['role'] = 'receptionist';

        $newUser = $userCreationService->createClinicStaff($clinic->id, $data);
        $newUser->unsetRelation('dentistProfile');

        return $this->successResponse($newUser, 'Recepcionista creado en clínica.', 201);
    }

    public function show(Clinic $clinic, int $id): JsonResponse
    {
        $user = $this->findClinicReceptionist($clinic->id, $id);

        return $this->successResponse($user, 'Recepcionista encontrado.');
    }

    public function update(Clinic $clinic, UpdateReceptionistRequest $request, int $id): JsonResponse
    {
        $user = $this->findClinicReceptionist($clinic->id, $id);
        $data = $request->validated();

        $data['role'] = 'receptionist';

        $user->fill(collect($data)->only(['name', 'email', 'phone', 'status', 'role'])->toArray());

        if (array_key_exists('password', $data) && filled($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return $this->successResponse($user->fresh(), 'Recepcionista actualizado.');
    }

    public function destroy(Clinic $clinic, int $id): JsonResponse
    {
        $user = $this->findClinicReceptionist($clinic->id, $id);
        $user->delete();

        return $this->successResponse([], 'Recepcionista eliminado.');
    }

    private function findClinicReceptionist(int $clinicId, int $id): User
    {
        return User::query()
            ->where('id', $id)
            ->where('clinic_id', $clinicId)
            ->where('role', 'receptionist')
            ->firstOrFail();
    }
}
