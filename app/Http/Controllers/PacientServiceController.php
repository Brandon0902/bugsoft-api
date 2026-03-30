<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Models\Service;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;

class PacientServiceController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AppointmentService $appointmentService)
    {
    }

    public function index(): JsonResponse
    {
        $authUser = request()->user();

        $services = Service::query()
            ->where('clinic_id', $authUser->clinic_id)
            ->where('status', true)
            ->with('specialty')
            ->latest()
            ->get();

        return $this->successResponse($services, 'Servicios disponibles listados.');
    }

    public function dentists(Service $service): JsonResponse
    {
        $service = $this->resolveScopedActiveService($service);

        if (! $service) {
            return $this->errorResponse('Servicio inválido.', ['service' => ['No existe en tu alcance.']], 404);
        }

        $dentists = $this->appointmentService->findCompatibleDentists(
            $service->clinic_id,
            (int) $service->specialty_id
        );

        return $this->successResponse($dentists, 'Dentistas compatibles listados.');
    }

    private function resolveScopedActiveService(Service $service): ?Service
    {
        return Service::query()
            ->where('id', $service->id)
            ->where('clinic_id', request()->user()->clinic_id)
            ->where('status', true)
            ->with('specialty')
            ->first();
    }
}
