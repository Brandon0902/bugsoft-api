<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;

class PacientAppointmentController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $authUser = request()->user();

        $appointments = Appointment::query()
            ->where('clinic_id', $authUser->clinic_id)
            ->where('patient_user_id', $authUser->id)
            ->with(['dentist:id,name,email'])
            ->orderBy('start_at')
            ->get();

        return $this->successResponse($appointments, 'Citas del paciente listadas.');
    }
}
