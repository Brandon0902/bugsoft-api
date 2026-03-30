<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Appointment\AvailableDentistsRequest;
use App\Http\Requests\Appointment\IndexAppointmentRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentStatusRequest;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class SuperClinicAppointmentController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AppointmentService $appointmentService)
    {
    }

    public function index(IndexAppointmentRequest $request, Clinic $clinic): JsonResponse
    {
        $query = Appointment::query()
            ->where('clinic_id', $clinic->id)
            ->with($this->appointmentRelations())
            ->orderBy('start_at');

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

        if ($request->filled('dentist_user_id')) {
            $query->where('dentist_user_id', $request->integer('dentist_user_id'));
        }

        if ($request->filled('patient_user_id')) {
            $query->where('patient_user_id', $request->integer('patient_user_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        return $this->successResponse($query->get(), 'Citas de clínica listadas.');
    }

    public function store(StoreAppointmentRequest $request, Clinic $clinic): JsonResponse
    {
        $data = $request->validated();
        $authUser = $request->user();

        $patient = $this->resolveScopedPatient($clinic->id, (int) $data['patient_user_id']);
        $dentist = $this->resolveScopedDentist($clinic->id, (int) $data['dentist_user_id']);
        $service = $this->resolveScopedService($clinic->id, (int) $data['service_id']);

        if (! $patient || ! $dentist || ! $service) {
            return $this->errorResponse('Paciente o dentista inválido.', ['appointment' => ['Paciente y dentista deben pertenecer a la clínica.']]);
        }

        if (! $this->dentistHasServiceSpecialty($dentist, $service)) {
            return $this->errorResponse(
                'Especialidad incompatible.',
                ['dentist_user_id' => ['El dentista seleccionado no cuenta con la especialidad requerida para este servicio.']]
            );
        }

        $startAt = Carbon::parse((string) $data['start_at']);
        $endAt = $startAt->copy()->addMinutes($service->duration_minutes);

        if ($this->appointmentService->hasDentistOverlap(
            $clinic->id,
            $dentist->id,
            $startAt->toDateTimeString(),
            $endAt->toDateTimeString(),
        )) {
            return $this->errorResponse('Choque de horario.', ['appointment' => ['El dentista ya tiene una cita en ese horario.']]);
        }

        $appointment = Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'patient_user_id' => $patient->id,
            'dentist_user_id' => $dentist->id,
            'service_id' => $service->id,
            'created_by' => $authUser->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => 'scheduled',
            'reason' => $data['reason'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? ($data['notes'] ?? null),
        ]);

        return $this->successResponse($appointment->load($this->appointmentRelations()), 'Cita creada en clínica.', 201);
    }

    public function show(Clinic $clinic, Appointment $appointment): JsonResponse
    {
        if (! $this->appointmentBelongsToClinic($appointment, $clinic->id)) {
            return $this->errorResponse('Cita no encontrada.', ['appointment' => ['No existe en la clínica indicada.']], 404);
        }

        return $this->successResponse(
            $appointment->load($this->appointmentRelations()),
            'Cita obtenida.'
        );
    }

    public function update(UpdateAppointmentRequest $request, Clinic $clinic, Appointment $appointment): JsonResponse
    {
        if (! $this->appointmentBelongsToClinic($appointment, $clinic->id)) {
            return $this->errorResponse('Cita no encontrada.', ['appointment' => ['No existe en la clínica indicada.']], 404);
        }

        $data = $request->validated();
        $patientId = (int) ($data['patient_user_id'] ?? $appointment->patient_user_id);
        $dentistId = (int) ($data['dentist_user_id'] ?? $appointment->dentist_user_id);
        $serviceId = array_key_exists('service_id', $data)
            ? (int) $data['service_id']
            : ($appointment->service_id !== null ? (int) $appointment->service_id : null);
        $startAt = Carbon::parse((string) ($data['start_at'] ?? $appointment->start_at->toDateTimeString()));

        $patient = $this->resolveScopedPatient($clinic->id, $patientId);
        if (! $patient) {
            return $this->errorResponse('Paciente inválido.', ['patient_user_id' => ['El paciente debe pertenecer a la clínica y tener rol pacient.']]);
        }

        $dentist = $this->resolveScopedDentist($clinic->id, $dentistId);
        if (! $dentist) {
            return $this->errorResponse('Dentista inválido.', ['dentist_user_id' => ['El dentista debe pertenecer a la clínica y tener rol dentist.']]);
        }

        $service = $serviceId !== null
            ? $this->resolveScopedService($clinic->id, $serviceId)
            : null;
        if ($serviceId !== null && ! $service) {
            return $this->errorResponse('Servicio inválido.', ['service_id' => ['El servicio debe pertenecer a la clínica.']]);
        }

        if ($service && ! $this->dentistHasServiceSpecialty($dentist, $service)) {
            return $this->errorResponse(
                'Especialidad incompatible.',
                ['dentist_user_id' => ['El dentista seleccionado no cuenta con la especialidad requerida para este servicio.']]
            );
        }

        $shouldRecalculateSchedule = array_key_exists('service_id', $data) || array_key_exists('start_at', $data);
        if ($shouldRecalculateSchedule && ! $service) {
            return $this->errorResponse('Servicio inválido.', ['service_id' => ['La cita requiere un servicio válido para recalcular el horario.']]);
        }

        $endAt = $shouldRecalculateSchedule
            ? $startAt->copy()->addMinutes($service->duration_minutes)
            : Carbon::parse($appointment->end_at->toDateTimeString());

        $shouldValidateOverlap = $shouldRecalculateSchedule || $dentist->id !== $appointment->dentist_user_id;

        if ($shouldValidateOverlap && $this->appointmentService->hasDentistOverlap(
            $clinic->id,
            $dentist->id,
            $startAt->toDateTimeString(),
            $endAt->toDateTimeString(),
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
        if ($service) {
            $appointment->service_id = $service->id;
        }
        if ($shouldRecalculateSchedule) {
            $appointment->start_at = $startAt;
            $appointment->end_at = $endAt;
        }
        $appointment->save();

        return $this->successResponse(
            $appointment->load($this->appointmentRelations()),
            'Cita actualizada.'
        );
    }

    public function availableDentists(AvailableDentistsRequest $request, Clinic $clinic): JsonResponse
    {
        $validated = $request->validated();

        $service = $this->resolveScopedService($clinic->id, (int) $validated['service_id']);

        if (! $service) {
            return $this->errorResponse('Servicio inválido.', ['service_id' => ['El servicio debe pertenecer a la clínica.']]);
        }

        $excludeAppointmentId = isset($validated['exclude_appointment_id'])
            ? (int) $validated['exclude_appointment_id']
            : null;
        $startAt = Carbon::parse((string) $validated['start_at']);
        $endAt = $this->appointmentService->calculateEndAtFromServiceDuration(
            $startAt->toDateTimeString(),
            (int) $service->duration_minutes
        );

        if ($excludeAppointmentId !== null && ! Appointment::query()
            ->where('id', $excludeAppointmentId)
            ->where('clinic_id', $clinic->id)
            ->exists()
        ) {
            return $this->errorResponse(
                'Cita a excluir inválida.',
                ['exclude_appointment_id' => ['La cita a excluir debe pertenecer a la clínica.']]
            );
        }

        $dentists = $this->appointmentService->findAvailableDentists(
            $clinic->id,
            (int) $service->specialty_id,
            $startAt->toDateTimeString(),
            $endAt->toDateTimeString(),
            $excludeAppointmentId,
        );

        return response()->json([
            'success' => true,
            'message' => 'Available dentists fetched successfully',
            'data' => $dentists,
            'meta' => [
                'service_id' => $service->id,
                'requested_start_at' => $startAt->toDateTimeString(),
                'requested_end_at' => $endAt->toDateTimeString(),
                'duration_minutes' => $service->duration_minutes,
            ],
        ]);
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, Clinic $clinic, Appointment $appointment): JsonResponse
    {
        if (! $this->appointmentBelongsToClinic($appointment, $clinic->id)) {
            return $this->errorResponse('Cita no encontrada.', ['appointment' => ['No existe en la clínica indicada.']], 404);
        }

        $appointment->update(['status' => (string) $request->string('status')]);

        return $this->successResponse($appointment, 'Estado de cita actualizado.');
    }

    private function appointmentBelongsToClinic(Appointment $appointment, int $clinicId): bool
    {
        return $appointment->clinic_id === $clinicId;
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

    private function resolveScopedService(int $clinicId, int $serviceId): ?Service
    {
        return Service::query()
            ->where('id', $serviceId)
            ->where('clinic_id', $clinicId)
            ->first();
    }

    private function dentistHasServiceSpecialty(User $dentist, Service $service): bool
    {
        return $dentist->dentistProfile()
            ->whereHas('specialties', fn ($query) => $query->where('specialties.id', $service->specialty_id))
            ->exists();
    }

    private function appointmentRelations(): array
    {
        return [
            'patient:id,name,email',
            'dentist:id,name,email',
            'service:id,name,specialty_id,duration_minutes',
            'service.specialty:id,name',
        ];
    }
}
