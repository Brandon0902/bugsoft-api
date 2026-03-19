<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $services = Service::query()
            ->where('clinic_id', request()->user()->clinic_id)
            ->with('specialty')
            ->latest()
            ->get();

        return $this->successResponse($services, 'Servicios listados.');
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = Service::query()->create([
            ...$request->validated(),
            'clinic_id' => request()->user()->clinic_id,
            'status' => $request->validated('status', true),
        ]);

        return $this->successResponse($service->load('specialty'), 'Servicio creado.', 201);
    }

    public function show(Service $service): JsonResponse
    {
        $service = $this->findClinicService($service);

        return $this->successResponse($service->load('specialty'), 'Servicio encontrado.');
    }

    public function update(Service $service, UpdateServiceRequest $request): JsonResponse
    {
        $service = $this->findClinicService($service);

        $service->fill($request->validated());
        $service->save();

        return $this->successResponse($service->fresh()->load('specialty'), 'Servicio actualizado.');
    }

    public function destroy(Service $service): JsonResponse
    {
        $service = $this->findClinicService($service);
        $service->delete();

        return $this->successResponse([], 'Servicio eliminado.');
    }

    private function findClinicService(Service $service): Service
    {
        return Service::query()
            ->where('id', $service->id)
            ->where('clinic_id', request()->user()->clinic_id)
            ->firstOrFail();
    }
}
