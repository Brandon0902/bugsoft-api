<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Appointment\AvailableDentistsRequest;
use App\Http\Requests\Appointment\IndexAppointmentRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentStatusRequest;
use App\Models\Appointment;
use App\Models\Service;
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
            ->with($this->appointmentRelations())
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
        $service = $this->resolveScopedService($authUser->clinic_id, (int) $data['service_id']);

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
            $authUser->clinic_id,
            $dentist->id,
            $startAt->toDateTimeString(),
            $endAt->toDateTimeString(),
        )) {
            return $this->errorResponse('Choque de horario.', ['appointment' => ['El dentista ya tiene una cita en ese horario.']]);
        }

        $appointment = Appointment::query()->create([
            'clinic_id' => $authUser->clinic_id,
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

        return $this->successResponse($appointment->load($this->appointmentRelations()), 'Cita creada.', 201);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $authUser = request()->user();

        if (! $this->canAccessAppointment($appointment, $authUser->clinic_id, $authUser->role === 'dentist' ? $authUser->id : null)) {
            return $this->errorResponse('Cita no encontrada.', ['appointment' => ['No existe en tu alcance.']], 404);
        }

        return $this->successResponse(
            $appointment->load($this->appointmentRelations()),
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
        $serviceId = (int) ($data['service_id'] ?? $appointment->service_id);
        $startAt = Carbon::parse((string) ($data['start_at'] ?? $appointment->start_at->toDateTimeString()));

        $patient = $this->resolveScopedPatient($authUser->clinic_id, $patientId);

        if (! $patient) {
            return $this->errorResponse('Paciente inválido.', ['patient_user_id' => ['El paciente debe pertenecer a la clínica y tener rol pacient.']]);
        }

        $dentist = $this->resolveScopedDentist($authUser->clinic_id, $dentistId);
        $service = $this->resolveScopedService($authUser->clinic_id, $serviceId);

        if (! $dentist) {
            return $this->errorResponse('Dentista inválido.', ['dentist_user_id' => ['El dentista debe pertenecer a la clínica y tener rol dentist.']]);
        }

        if (! $service) {
            return $this->errorResponse('Servicio inválido.', ['service_id' => ['El servicio debe pertenecer a la clínica.']]);
        }

        if (! $this->dentistHasServiceSpecialty($dentist, $service)) {
            return $this->errorResponse(
                'Especialidad incompatible.',
                ['dentist_user_id' => ['El dentista seleccionado no cuenta con la especialidad requerida para este servicio.']]
            );
        }

        $shouldRecalculateSchedule = array_key_exists('service_id', $data) || array_key_exists('start_at', $data);
        $endAt = $shouldRecalculateSchedule
            ? $startAt->copy()->addMinutes($service->duration_minutes)
            : Carbon::parse($appointment->end_at->toDateTimeString());

        if ($this->appointmentService->hasDentistOverlap(
            $authUser->clinic_id,
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
        $appointment->service_id = $service->id;
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

    public function availableDentists(AvailableDentistsRequest $request): JsonResponse
    {
        $authUser = $request->user();
        $validated = $request->validated();

        $service = $this->resolveScopedService($authUser->clinic_id, (int) $validated['service_id']);

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
            ->where('clinic_id', $authUser->clinic_id)
            ->exists()
        ) {
            return $this->errorResponse(
                'Cita a excluir inválida.',
                ['exclude_appointment_id' => ['La cita a excluir debe pertenecer a la clínica.']]
            );
        }

        $dentists = $this->appointmentService->findAvailableDentists(
            $authUser->clinic_id,
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
