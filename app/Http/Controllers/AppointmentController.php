<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Appointment\IndexAppointmentRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentStatusRequest;
use App\Models\Appointment;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AppointmentController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AppointmentService $appointmentService)
    {
    }

    public function index(IndexAppointmentRequest $request): JsonResponse
    {
        $authUser = $request->user();

        $query = Appointment::query()
            ->where('clinic_id', $authUser->clinic_id)
            ->with(['patient:id,name,email', 'dentist:id,name,email'])
            ->orderBy('start_at');

        if ($authUser->role === 'dentist') {
            $query->where('dentist_user_id', $authUser->id);
        } elseif ($request->filled('dentist_user_id')) {
            $query->where('dentist_user_id', $request->integer('dentist_user_id'));
        }

        if ($request->filled('date') && ! $request->filled('from') && ! $request->filled('to')) {
            $date = Carbon::parse((string) $request->string('date'));
            $query->whereBetween('start_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
        }

        if ($request->filled('from')) {
            $query->where('start_at', '>=', (string) $request->string('from'));
        }

        if ($request->filled('to')) {
            $query->where('end_at', '<=', (string) $request->string('to'));
        }

        if ($request->filled('patient_user_id')) {
            $query->where('patient_user_id', $request->integer('patient_user_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        return $this->successResponse($query->get(), 'Citas listadas.');
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $authUser = $request->user();
        $data = $request->validated();

        $patient = $this->resolveScopedPatient($authUser->clinic_id, (int) $data['patient_user_id']);
        $dentistId = $authUser->role === 'dentist' ? $authUser->id : (int) $data['dentist_user_id'];
        $dentist = $this->resolveScopedDentist($authUser->clinic_id, $dentistId);

        if (! $patient || ! $dentist) {
            return $this->errorResponse('Paciente o dentista inválido.', ['appointment' => ['Paciente y dentista deben pertenecer a la clínica.']]);
        }

        if ($this->appointmentService->hasDentistOverlap($authUser->clinic_id, $dentist->id, (string) $data['start_at'], (string) $data['end_at'])) {
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
            'internal_notes' => $data['internal_notes'] ?? ($data['notes'] ?? null),
        ]);

        return $this->successResponse($appointment->load(['patient:id,name,email', 'dentist:id,name,email']), 'Cita creada.', 201);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $authUser = request()->user();

        if (! $this->canAccessAppointment($appointment, $authUser->clinic_id, $authUser->role === 'dentist' ? $authUser->id : null)) {
            return $this->errorResponse('Cita no encontrada.', ['appointment' => ['No existe en tu alcance.']], 404);
        }

        return $this->successResponse(
            $appointment->load(['patient:id,name,email,phone', 'dentist:id,name,email,phone']),
            'Cita obtenida.'
        );
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $authUser = $request->user();

        if (! $this->canAccessAppointment($appointment, $authUser->clinic_id, $authUser->role === 'dentist' ? $authUser->id : null)) {
            return $this->errorResponse('Cita no encontrada.', ['appointment' => ['No existe en tu alcance.']], 404);
        }

        $data = $request->validated();

        $patientId = (int) ($data['patient_user_id'] ?? $appointment->patient_user_id);
        $dentistId = $authUser->role === 'dentist'
            ? $authUser->id
            : (int) ($data['dentist_user_id'] ?? $appointment->dentist_user_id);
        $startAt = (string) ($data['start_at'] ?? $appointment->start_at->toDateTimeString());
        $endAt = (string) ($data['end_at'] ?? $appointment->end_at->toDateTimeString());

        $patient = $this->resolveScopedPatient($authUser->clinic_id, $patientId);

        if (! $patient) {
            return $this->errorResponse('Paciente inválido.', ['patient_user_id' => ['El paciente debe pertenecer a la clínica y tener rol pacient.']]);
        }

        $dentist = $this->resolveScopedDentist($authUser->clinic_id, $dentistId);

        if (! $dentist) {
            return $this->errorResponse('Dentista inválido.', ['dentist_user_id' => ['El dentista debe pertenecer a la clínica y tener rol dentist.']]);
        }

        if (Carbon::parse($endAt)->lessThanOrEqualTo(Carbon::parse($startAt))) {
            return $this->errorResponse('Rango de horario inválido.', ['end_at' => ['end_at debe ser mayor que start_at.']]);
        }

        if ($this->appointmentService->hasDentistOverlap(
            $authUser->clinic_id,
            $dentist->id,
            $startAt,
            $endAt,
            $appointment->id,
        )) {
            return $this->errorResponse('Choque de horario.', ['appointment' => ['El dentista ya tiene una cita en ese horario.']]);
        }

        if (array_key_exists('notes', $data) && ! array_key_exists('internal_notes', $data)) {
            $data['internal_notes'] = $data['notes'];
        }

        $appointment->fill($data);
        $appointment->patient_user_id = $patient->id;
        $appointment->dentist_user_id = $dentist->id;
        $appointment->save();

        return $this->successResponse(
            $appointment->load(['patient:id,name,email,phone', 'dentist:id,name,email,phone']),
            'Cita actualizada.'
        );
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment): JsonResponse
    {
        $authUser = $request->user();

        if (! $this->canAccessAppointment($appointment, $authUser->clinic_id, $authUser->role === 'dentist' ? $authUser->id : null)) {
            return $this->errorResponse('Cita no encontrada.', ['appointment' => ['No existe en tu alcance.']], 404);
        }

        $appointment->update(['status' => (string) $request->string('status')]);

        return $this->successResponse($appointment, 'Estado de cita actualizado.');
    }

    private function canAccessAppointment(Appointment $appointment, int $clinicId, ?int $dentistId = null): bool
    {
        if ($appointment->clinic_id !== $clinicId) {
            return false;
        }

        if ($dentistId !== null && $appointment->dentist_user_id !== $dentistId) {
            return false;
        }

        return true;
    }

    private function resolveScopedPatient(int $clinicId, int $patientId): ?User
    {
        return User::query()
            ->where('id', $patientId)
            ->where('clinic_id', $clinicId)
            ->where('role', 'pacient')
            ->first();
    }

    private function resolveScopedDentist(int $clinicId, int $dentistId): ?User
    {
        return User::query()
            ->where('id', $dentistId)
            ->where('clinic_id', $clinicId)
            ->where('role', 'dentist')
            ->first();
    }
}
