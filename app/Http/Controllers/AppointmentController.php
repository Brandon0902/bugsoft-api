<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Appointment\IndexAppointmentsRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentStatusRequest;
use App\Models\Appointment;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;

class AppointmentController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AppointmentService $appointmentService)
    {
    }

    public function index(IndexAppointmentsRequest $request): JsonResponse
    {
        $authUser = $request->user();
        $query = Appointment::query()
            ->where('clinic_id', $authUser->clinic_id)
            ->with(['patient:id,name,email', 'dentist:id,name,email'])
            ->orderBy('start_at');

        if ($request->filled('from')) {
            $query->where('start_at', '>=', $request->string('from'));
        }

        if ($request->filled('to')) {
            $query->where('end_at', '<=', $request->string('to'));
        }

        return $this->successResponse($query->get(), 'Citas listadas.');
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $authUser = $request->user();
        $data = $request->validated();

        $patient = User::query()
            ->where('id', $data['patient_user_id'])
            ->where('clinic_id', $authUser->clinic_id)
            ->where('role', 'client')
            ->first();

        $dentist = User::query()
            ->where('id', $data['dentist_user_id'])
            ->where('clinic_id', $authUser->clinic_id)
            ->where('role', 'dentist')
            ->first();

        if (! $patient || ! $dentist) {
            return $this->errorResponse('Paciente o dentista inválido.', ['appointment' => ['Paciente y dentista deben pertenecer a la clínica.']]);
        }

        if ($this->appointmentService->hasDentistOverlap($authUser->clinic_id, $dentist->id, $data['start_at'], $data['end_at'])) {
            return $this->errorResponse('Choque de horario.', ['appointment' => ['El dentista ya tiene una cita en ese horario.']]);
        }

        $appointment = Appointment::query()->create([
            'clinic_id' => $authUser->clinic_id,
            'patient_user_id' => $patient->id,
            'dentist_user_id' => $dentist->id,
            'created_by' => $authUser->id,
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'status' => 'scheduled',
            'reason' => $data['reason'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
        ]);

        return $this->successResponse($appointment->load(['patient:id,name,email', 'dentist:id,name,email']), 'Cita creada.', 201);
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment): JsonResponse
    {
        $authUser = $request->user();

        if ($appointment->clinic_id !== $authUser->clinic_id) {
            return $this->errorResponse('Cita no encontrada.', ['appointment' => ['No existe en tu clínica.']], 404);
        }

        $appointment->update(['status' => $request->string('status')]);

        return $this->successResponse($appointment, 'Estado de cita actualizado.');
    }
}
