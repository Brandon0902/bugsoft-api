<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Models\Clinic;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PacientClinicController extends Controller
{
    use ApiResponse;

    public function show(): JsonResponse
    {
        $authUser = request()->user();

        $clinic = Clinic::query()
            ->where('id', $authUser->clinic_id)
            ->first();

        if (! $clinic) {
            return $this->errorResponse('Clínica no encontrada.', ['clinic' => ['No existe en tu alcance.']], 404);
        }

        $services = Service::query()
            ->where('clinic_id', $clinic->id)
            ->where('status', true)
            ->with('specialty:id,name')
            ->latest()
            ->get(['id', 'clinic_id', 'specialty_id', 'name', 'price', 'duration_minutes']);

        $dentists = User::query()
            ->where('clinic_id', $clinic->id)
            ->where('role', 'dentist')
            ->with(['dentistProfile:id,user_id,clinic_id,license_number,color'])
            ->latest()
            ->get(['id', 'clinic_id', 'name', 'email', 'phone']);

        return $this->successResponse([
            'clinic' => [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'email' => $clinic->email,
                'phone' => $clinic->phone,
                'address' => $clinic->address,
            ],
            'services' => $services,
            'dentists' => $dentists,
        ], 'Información de la clínica obtenida.');
    }
}
