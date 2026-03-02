<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Models\DentistProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $user = request()->user();

        $users = User::query()
            ->where('clinic_id', $user->clinic_id)
            ->where('id', '!=', $user->id)
            ->latest()
            ->get();

        return $this->successResponse($users, 'Usuarios de clínica listados.');
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $authUser = request()->user();
        $data = $request->validated();

        $newUser = User::query()->create([
            'clinic_id' => $authUser->clinic_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'status' => $data['status'] ?? true,
        ]);

        if ($newUser->role === 'dentist') {
            DentistProfile::query()->create([
                'user_id' => $newUser->id,
                'clinic_id' => $authUser->clinic_id,
            ]);
        }

        return $this->successResponse($newUser, 'Usuario creado en clínica.', 201);
    }
}
