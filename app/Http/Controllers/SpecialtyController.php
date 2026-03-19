<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Specialty\StoreSpecialtyRequest;
use App\Http\Requests\Specialty\UpdateSpecialtyRequest;
use App\Models\Specialty;
use Illuminate\Http\JsonResponse;

class SpecialtyController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $specialties = Specialty::query()->latest()->get();

        return $this->successResponse($specialties, 'Especialidades listadas.');
    }

    public function store(StoreSpecialtyRequest $request): JsonResponse
    {
        $specialty = Specialty::query()->create([
            ...$request->validated(),
            'status' => $request->validated('status', true),
        ]);

        return $this->successResponse($specialty, 'Especialidad creada.', 201);
    }

    public function show(Specialty $specialty): JsonResponse
    {
        return $this->successResponse($specialty, 'Especialidad encontrada.');
    }

    public function update(Specialty $specialty, UpdateSpecialtyRequest $request): JsonResponse
    {
        $specialty->fill($request->validated());
        $specialty->save();

        return $this->successResponse($specialty->fresh(), 'Especialidad actualizada.');
    }

    public function destroy(Specialty $specialty): JsonResponse
    {
        $specialty->delete();

        return $this->successResponse([], 'Especialidad eliminada.');
    }
}
