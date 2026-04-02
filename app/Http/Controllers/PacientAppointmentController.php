<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Pacient\ExportPacientAppointmentsRequest;
use App\Http\Requests\Pacient\RespondAppointmentConfirmationRequest;
use App\Http\Requests\Pacient\StorePacientAppointmentRequest;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class PacientAppointmentController extends Controller
{
    use ApiResponse;

    private const NON_EDITABLE_CONFIRMATION_STATUSES = [
        'canceled',
        'confirmed',
        'completed',
        'no_show',
    ];

    public function __construct(private readonly AppointmentService $appointmentService)
    {
    }

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

    public function show(int $appointment): JsonResponse
    {
        $appointmentModel = $this->resolveScopedAppointment($appointment);

        if (! $appointmentModel) {
            return $this->errorResponse('Cita no encontrada.', ['appointment' => ['No existe en tu alcance.']], 404);
        }

        return $this->successResponse(
            $this->appointmentPayload($appointmentModel),
            'Cita del paciente obtenida.'
        );
    }

    public function export(ExportPacientAppointmentsRequest $request): JsonResponse
    {
        $authUser = $request->user();
        $filters = $request->validated();

        $appointments = Appointment::query()
            ->where('clinic_id', $authUser->clinic_id)
            ->where('patient_user_id', $authUser->id)
            ->when(
                isset($filters['from']),
                fn ($query) => $query->where('start_at', '>=', Carbon::parse($filters['from'])->startOfDay())
            )
            ->when(
                isset($filters['to']),
                fn ($query) => $query->where('start_at', '<=', Carbon::parse($filters['to'])->endOfDay())
            )
            ->when(
                isset($filters['status']),
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->with([
                'clinic:id,name',
                'dentist:id,name,email',
                'service:id,clinic_id,specialty_id,name,duration_minutes,price,status',
                'service.specialty:id,name',
                'notes' => fn ($query) => $query
                    ->with('author:id,name,email')
                    ->orderBy('created_at'),
            ])
            ->orderBy('start_at')
            ->get();

        $payload = [
            'patient' => [
                'id' => $authUser->id,
                'name' => $authUser->name,
                'email' => $authUser->email,
            ],
            'generated_at' => now()->toISOString(),
            'filters' => [
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
            'summary' => [
                'total_appointments' => $appointments->count(),
                'total_notes' => $appointments->sum(fn (Appointment $appointment) => $appointment->notes->count()),
            ],
            'appointments' => $appointments->map(fn (Appointment $appointment) => $this->exportAppointmentPayload($appointment))->values(),
        ];

        return $this->successResponse($payload, 'Historial de citas exportado.');
    }

    public function store(StorePacientAppointmentRequest $request): JsonResponse
    {
        $authUser = $request->user();
        $data = $request->validated();

        $service = $this->resolveScopedActiveService((int) $data['service_id']);

        if (! $service) {
            return $this->errorResponse('Servicio inválido.', ['service_id' => ['El servicio debe pertenecer a tu clínica y estar activo.']], 404);
        }

        $dentist = $this->resolveCompatibleDentist($service, (int) $data['dentist_user_id']);

        if (! $dentist) {
            return $this->errorResponse('Dentista inválido.', ['dentist_user_id' => ['El dentista debe pertenecer a la clínica, tener rol dentist y estar activo.']], 422);
        }

        if (! $this->dentistHasServiceSpecialty($dentist, $service)) {
            return $this->errorResponse(
                'Especialidad incompatible.',
                ['dentist_user_id' => ['El dentista seleccionado no cuenta con la especialidad requerida para este servicio.']]
            );
        }

        $startAt = Carbon::parse((string) $data['start_at']);
        $endAt = $this->appointmentService->calculateEndAtFromServiceDuration(
            $startAt->toDateTimeString(),
            (int) $service->duration_minutes
        );

        if ($this->appointmentService->hasDentistOverlap(
            $service->clinic_id,
            $dentist->id,
            $startAt->toDateTimeString(),
            $endAt->toDateTimeString(),
        )) {
            return $this->errorResponse('Choque de horario.', ['appointment' => ['El dentista ya tiene una cita en el horario seleccionado.']]);
        }

        $appointment = Appointment::query()->create([
            'clinic_id' => $service->clinic_id,
            'patient_user_id' => $authUser->id,
            'dentist_user_id' => $dentist->id,
            'service_id' => $service->id,
            'created_by' => $authUser->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => 'scheduled',
            'reason' => $data['reason'] ?? null,
            'internal_notes' => $data['notes'] ?? null,
        ]);

        return $this->successResponse(
            $appointment->load($this->appointmentRelationsForStore()),
            'Cita creada.',
            201
        );
    }

    public function respondConfirmation(RespondAppointmentConfirmationRequest $request, int $appointment): JsonResponse
    {
        $appointmentModel = $this->resolveScopedAppointment($appointment);

        if (! $appointmentModel) {
            return $this->errorResponse('Cita no encontrada.', ['appointment' => ['No existe en tu alcance.']], 404);
        }

        if (in_array($appointmentModel->status, self::NON_EDITABLE_CONFIRMATION_STATUSES, true)) {
            return $this->errorResponse(
                'La cita ya no puede responderse.',
                ['appointment' => ['La cita ya fue respondida o se encuentra en un estado final.']]
            );
        }

        if ($this->appointmentAlreadyStarted($appointmentModel)) {
            return $this->errorResponse(
                'La cita ya no puede responderse porque ya ocurrió.',
                ['appointment' => ['La cita ya inició o ya pasó.']]
            );
        }

        if (! $this->confirmationWindowOpen($appointmentModel)) {
            return $this->errorResponse(
                'La cita aún no está disponible para confirmación.',
                ['appointment' => ['La confirmación solo está disponible dentro de los 3 días previos a la cita.']]
            );
        }

        $response = (string) $request->string('response');
        $appointmentModel->status = $response === 'yes' ? 'confirmed' : 'canceled';
        $appointmentModel->save();

        return $this->successResponse(
            $this->appointmentPayload($appointmentModel->fresh()),
            $response === 'yes'
                ? 'Cita confirmada por el paciente.'
                : 'Cita cancelada por el paciente.'
        );
    }

    private function resolveScopedAppointment(int $appointmentId): ?Appointment
    {
        $authUser = request()->user();

        return Appointment::query()
            ->where('id', $appointmentId)
            ->where('clinic_id', $authUser->clinic_id)
            ->where('patient_user_id', $authUser->id)
            ->with($this->appointmentRelations())
            ->first();
    }

    private function resolveScopedActiveService(int $serviceId): ?Service
    {
        return Service::query()
            ->where('id', $serviceId)
            ->where('clinic_id', request()->user()->clinic_id)
            ->where('status', true)
            ->first();
    }

    private function resolveCompatibleDentist(Service $service, int $dentistId): ?User
    {
        return User::query()
            ->where('id', $dentistId)
            ->where('clinic_id', $service->clinic_id)
            ->where('role', 'dentist')
            ->where('status', true)
            ->with([
                'dentistProfile:id,user_id,clinic_id,license_number,color',
                'dentistProfile.specialties:id,name',
            ])
            ->first();
    }

    private function dentistHasServiceSpecialty(User $dentist, Service $service): bool
    {
        return $dentist->dentistProfile()
            ->whereHas('specialties', fn ($query) => $query->where('specialties.id', $service->specialty_id))
            ->exists();
    }

    private function appointmentPayload(Appointment $appointment): array
    {
        $appointment->loadMissing($this->appointmentRelations());

        return [
            ...$appointment->toArray(),
            'confirmation_window_open' => $this->confirmationWindowOpen($appointment),
            'can_patient_confirm' => $this->canPatientConfirm($appointment),
            'confirmation_response_available_at' => $appointment->start_at
                ->copy()
                ->subDays(3)
                ->toISOString(),
        ];
    }

    private function canPatientConfirm(Appointment $appointment): bool
    {
        return ! in_array($appointment->status, self::NON_EDITABLE_CONFIRMATION_STATUSES, true)
            && $this->confirmationWindowOpen($appointment)
            && ! $this->appointmentAlreadyStarted($appointment);
    }

    private function confirmationWindowOpen(Appointment $appointment): bool
    {
        $now = Carbon::now();
        $availableAt = $appointment->start_at->copy()->subDays(3);

        return $now->greaterThanOrEqualTo($availableAt)
            && $now->lt($appointment->start_at);
    }

    private function appointmentAlreadyStarted(Appointment $appointment): bool
    {
        return Carbon::now()->greaterThanOrEqualTo($appointment->start_at);
    }

    private function appointmentRelations(): array
    {
        return [
            'dentist:id,name,email',
            'service:id,name,specialty_id,duration_minutes,price,status',
            'service.specialty:id,name',
        ];
    }

    private function exportAppointmentPayload(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'status' => $appointment->status,
            'start_at' => $appointment->start_at?->toDateTimeString(),
            'end_at' => $appointment->end_at?->toDateTimeString(),
            'clinic' => $appointment->clinic
                ? [
                    'id' => $appointment->clinic->id,
                    'name' => $appointment->clinic->name,
                ]
                : null,
            'dentist' => $appointment->dentist
                ? [
                    'id' => $appointment->dentist->id,
                    'name' => $appointment->dentist->name,
                    'email' => $appointment->dentist->email,
                ]
                : null,
            'service' => $appointment->service
                ? [
                    'id' => $appointment->service->id,
                    'name' => $appointment->service->name,
                    'duration_minutes' => $appointment->service->duration_minutes,
                    'price' => $appointment->service->price,
                ]
                : null,
            'specialty' => $appointment->service?->specialty
                ? [
                    'id' => $appointment->service->specialty->id,
                    'name' => $appointment->service->specialty->name,
                ]
                : null,
            'notes' => $appointment->notes->map(function ($note): array {
                return [
                    'id' => $note->id,
                    'note' => $note->note,
                    'created_at' => $note->created_at?->toISOString(),
                    'author' => $note->author
                        ? [
                            'id' => $note->author->id,
                            'name' => $note->author->name,
                            'email' => $note->author->email,
                        ]
                        : null,
                ];
            })->values()->all(),
        ];
    }

    private function appointmentRelationsForStore(): array
    {
        return [
            'patient:id,name,email',
            'dentist:id,name,email',
            'dentist.dentistProfile:id,user_id,clinic_id,license_number,color',
            'dentist.dentistProfile.specialties:id,name',
            'service:id,name,specialty_id,duration_minutes,price,status,clinic_id',
            'service.specialty:id,name',
        ];
    }
}
