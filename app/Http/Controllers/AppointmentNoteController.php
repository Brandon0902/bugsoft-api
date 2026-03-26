<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\AppointmentNote\StoreAppointmentNoteRequest;
use App\Models\Appointment;
use App\Models\AppointmentNote;
use Illuminate\Http\JsonResponse;

class AppointmentNoteController extends Controller
{
    use ApiResponse;

    public function index(int $appointment): JsonResponse
    {
        $appointmentModel = $this->resolveDentistAppointment($appointment);

        if (! $appointmentModel) {
            return $this->errorResponse('Cita no encontrada.', ['appointment' => ['No existe en tu alcance.']], 404);
        }

        $notes = $appointmentModel->notes()
            ->with('author:id,name,email')
            ->orderByDesc('created_at')
            ->get();

        return $this->successResponse($notes, 'Notas de cita listadas.');
    }

    public function store(StoreAppointmentNoteRequest $request, int $appointment): JsonResponse
    {
        $appointmentModel = $this->resolveDentistAppointment($appointment);

        if (! $appointmentModel) {
            return $this->errorResponse('Cita no encontrada.', ['appointment' => ['No existe en tu alcance.']], 404);
        }

        $note = AppointmentNote::query()->create([
            'appointment_id' => $appointmentModel->id,
            'author_user_id' => $request->user()->id,
            'note' => (string) $request->string('note'),
        ]);

        return $this->successResponse(
            $note->load('author:id,name,email'),
            'Nota de cita creada.',
            201
        );
    }

    public function show(int $appointment, int $note): JsonResponse
    {
        $appointmentModel = $this->resolveDentistAppointment($appointment);

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

    private function resolveDentistAppointment(int $appointmentId): ?Appointment
    {
        $authUser = request()->user();

        return Appointment::query()
            ->where('id', $appointmentId)
            ->where('clinic_id', $authUser->clinic_id)
            ->where('dentist_user_id', $authUser->id)
            ->first();
    }
}
