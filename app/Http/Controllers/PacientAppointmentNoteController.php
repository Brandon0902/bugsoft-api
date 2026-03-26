<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;

class PacientAppointmentNoteController extends Controller
{
    use ApiResponse;

    public function index(int $appointment): JsonResponse
    {
        $appointmentModel = $this->resolvePacientAppointment($appointment);

        if (! $appointmentModel) {
            return $this->errorResponse('Cita no encontrada.', ['appointment' => ['No existe en tu alcance.']], 404);
        }

        $notes = $appointmentModel->notes()
            ->with('author:id,name,email')
            ->orderByDesc('created_at')
            ->get();

        return $this->successResponse($notes, 'Notas de cita listadas.');
    }

    public function show(int $appointment, int $note): JsonResponse
    {
        $appointmentModel = $this->resolvePacientAppointment($appointment);

        if (! $appointmentModel) {
            return $this->errorResponse('Cita no encontrada.', ['appointment' => ['No existe en tu alcance.']], 404);
        }

        $noteModel = $appointmentModel->notes()
            ->with('author:id,name,email')
            ->whereKey($note)
            ->first();

        if (! $noteModel) {
            return $this->errorResponse('Nota no encontrada.', ['note' => ['No existe en tu alcance.']], 404);
        }

        return $this->successResponse($noteModel, 'Nota de cita obtenida.');
    }

    private function resolvePacientAppointment(int $appointmentId): ?Appointment
    {
        $authUser = request()->user();

        return Appointment::query()
            ->where('id', $appointmentId)
            ->where('clinic_id', $authUser->clinic_id)
            ->where('patient_user_id', $authUser->id)
            ->first();
    }
}
