<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Models\Clinic;
use App\Models\User;
use App\Services\UserCreationService;
use Illuminate\Http\JsonResponse;

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
}
