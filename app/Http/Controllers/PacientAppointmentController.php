<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Requests\Pacient\RespondAppointmentConfirmationRequest;
use App\Models\Appointment;
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
}
