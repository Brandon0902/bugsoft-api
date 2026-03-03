<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Clinic\UpdateClinicRequest;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;

class AdminClinicController extends Controller
{
    use ApiResponse;

    public function show(): JsonResponse
    {
        $clinic = $this->resolveClinic();

        if (! $clinic) {
            return $this->errorResponse('Clinic not found for this admin.', ['clinic' => ['No clinic assigned.']], 404);
        }

        return $this->successResponse($clinic->loadCount(['users', 'appointments']), 'Clinic retrieved.');
    }

    public function update(UpdateClinicRequest $request): JsonResponse
    {
        $clinic = $this->resolveClinic();

        if (! $clinic) {
            return $this->errorResponse('Clinic not found for this admin.', ['clinic' => ['No clinic assigned.']], 404);
        }

        $clinic->fill($request->validated());
        $clinic->save();

        return $this->successResponse($clinic->fresh(), 'Clinic updated.');
    }

    private function resolveClinic(): ?Clinic
    {
        $user = request()->user();

        return $user?->clinic_id ? Clinic::query()->find($user->clinic_id) : null;
    }
}
