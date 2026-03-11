<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\User;
use App\Services\UserCreationService;
use Illuminate\Http\JsonResponse;

class AdminReceptionistController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $authUser = request()->user();

        $users = User::query()
            ->where('clinic_id', $authUser->clinic_id)
            ->where('role', 'receptionist')
            ->latest()
            ->get();

        return $this->successResponse($users, 'Recepcionistas de clínica listados.');
    }

    public function store(StoreAdminUserRequest $request, UserCreationService $userCreationService): JsonResponse
    {
        $authUser = request()->user();
        $data = $request->validated();
        $data['role'] = 'receptionist';

        $newUser = $userCreationService->createClinicStaff((int) $authUser->clinic_id, $data);

        return $this->successResponse($newUser, 'Recepcionista creado en clínica.', 201);
    }

    public function show(int $id): JsonResponse
    {
        $user = $this->findClinicReceptionist($id);

        return $this->successResponse($user, 'Recepcionista encontrado.');
    }

    public function update(UpdateAdminUserRequest $request, int $id): JsonResponse
    {
        $user = $this->findClinicReceptionist($id);
        $data = $request->validated();

        unset($data['clinic_id']);
        $data['role'] = 'receptionist';
        unset($data['dentist_profile']);

        $user->fill(collect($data)->only(['name', 'email', 'password', 'phone', 'status', 'role'])->toArray());
        $user->save();

        return $this->successResponse($user->fresh(), 'Recepcionista actualizado.');
    }

    public function destroy(int $id): JsonResponse
    {
        $user = $this->findClinicReceptionist($id);
        $user->delete();

        return $this->successResponse([], 'Recepcionista eliminado.');
    }

    private function findClinicReceptionist(int $id): User
    {
        return User::query()
            ->where('id', $id)
            ->where('clinic_id', request()->user()->clinic_id)
            ->where('role', 'receptionist')
            ->firstOrFail();
    }
}
