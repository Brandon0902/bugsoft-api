<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Clinic\StoreClinicWithAdminRequest;
use App\Http\Requests\Clinic\UpdateClinicRequest;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SuperClinicController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $clinics = Clinic::query()
            ->withCount(['users', 'appointments'])
            ->latest()
            ->paginate(15);

        return $this->successResponse($clinics, 'Clinics listed.');
    }

    public function show(Clinic $clinic): JsonResponse
    {
        $clinic->loadCount(['users', 'appointments']);

        return $this->successResponse($clinic, 'Clinic retrieved.');
    }

    public function store(StoreClinicWithAdminRequest $request): JsonResponse
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

            return [
                'clinic' => $clinic,
                'admin' => $admin,
            ];
        });

        return $this->successResponse($result, 'Clinic created.', 201);
    }

    public function update(UpdateClinicRequest $request, Clinic $clinic): JsonResponse
    {
        $clinic->fill($request->validated());
        $clinic->save();

        return $this->successResponse($clinic->fresh(), 'Clinic updated.');
    }

    public function destroy(Clinic $clinic): JsonResponse
    {
        try {
            $clinic->delete();
        } catch (QueryException) {
            return $this->errorResponse(
                'Clinic cannot be deleted because it has related records.',
                ['clinic' => ['Delete related appointments or records before deleting this clinic.']],
                409
            );
        }

        return $this->successResponse([], 'Clinic deleted.');
    }
}
