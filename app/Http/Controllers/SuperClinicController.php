<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Super\StoreClinicRequest;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SuperClinicController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->successResponse(Clinic::query()->latest()->get(), 'Clínicas listadas.');
    }

    public function store(StoreClinicRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $result = DB::transaction(function () use ($payload) {
            $clinic = Clinic::query()->create([
                'name' => $payload['clinic']['name'],
                'phone' => $payload['clinic']['phone'] ?? null,
                'email' => $payload['clinic']['email'] ?? null,
                'address' => $payload['clinic']['address'] ?? null,
                'status' => $payload['clinic']['status'] ?? true,
            ]);

            $admin = User::query()->create([
                'clinic_id' => $clinic->id,
                'name' => $payload['admin']['name'],
                'email' => $payload['admin']['email'],
                'password' => $payload['admin']['password'],
                'phone' => $payload['admin']['phone'] ?? null,
                'role' => 'admin',
                'status' => true,
            ]);

            return compact('clinic', 'admin');
        });

        return $this->successResponse($result, 'Clínica y admin creados.', 201);
    }
}
