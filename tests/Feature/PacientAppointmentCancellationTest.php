<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PacientAppointmentCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pacient_can_cancel_own_future_scheduled_appointment(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id);

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/cancel")
            ->assertOk()
            ->assertJsonPath('message', 'Cita cancelada correctamente.')
            ->assertJsonPath('data.status', 'canceled')
            ->assertJsonPath('data.can_patient_cancel', false);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'canceled',
        ]);
    }

    public function test_pacient_can_cancel_own_future_confirmed_appointment(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id, null, null, 'confirmed');

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'canceled')
            ->assertJsonPath('data.can_patient_cancel', false);
    }

    public function test_pacient_cannot_cancel_appointment_of_another_patient(): void
    {
        [$clinic, $dentist] = $this->makeClinicDentistAndPacient();
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $otherPatient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $appointment = $this->createAppointment($clinic->id, $otherPatient->id, $dentist->id, $dentist->id);

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/cancel")
            ->assertNotFound();
    }

    public function test_pacient_cannot_cancel_appointment_of_another_clinic(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $otherClinic = Clinic::query()->create(['name' => 'Clínica ajena']);
        $otherDentist = User::factory()->create(['clinic_id' => $otherClinic->id, 'role' => 'dentist']);
        $otherPatient = User::factory()->create(['clinic_id' => $otherClinic->id, 'role' => 'pacient']);
        $appointment = $this->createAppointment($otherClinic->id, $otherPatient->id, $otherDentist->id, $otherDentist->id);

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/cancel")
            ->assertNotFound();
    }

    public function test_pacient_cannot_cancel_already_canceled_appointment(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id, null, null, 'canceled');

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La cita ya no puede cancelarse.');
    }

    public function test_pacient_cannot_cancel_completed_appointment(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id, null, null, 'completed');

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La cita ya no puede cancelarse.');
    }

    public function test_pacient_cannot_cancel_no_show_appointment(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id, null, null, 'no_show');

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La cita ya no puede cancelarse.');
    }

    public function test_pacient_cannot_cancel_appointment_that_already_started_or_passed(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment(
            $clinic->id,
            $patient->id,
            $dentist->id,
            $dentist->id,
            now()->subMinutes(5)->format('Y-m-d H:i:s'),
            now()->addMinutes(25)->format('Y-m-d H:i:s')
        );

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La cita ya no puede cancelarse porque ya ocurrió o ya inició.');
    }

    public function test_dentist_cannot_use_patient_cancel_endpoint(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id);

        Sanctum::actingAs($dentist);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/cancel")
            ->assertForbidden();
    }

    public function test_receptionist_cannot_use_patient_cancel_endpoint(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Cancellation']);
        $receptionist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'receptionist']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $receptionist->id);

        Sanctum::actingAs($receptionist);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/cancel")
            ->assertForbidden();
    }

    public function test_admin_cannot_use_patient_cancel_endpoint(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Cancellation']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $admin->id);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/cancel")
            ->assertForbidden();
    }

    public function test_pacient_can_cancel_even_if_appointment_is_more_than_three_days_away(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment(
            $clinic->id,
            $patient->id,
            $dentist->id,
            $dentist->id,
            now()->addDays(10)->format('Y-m-d H:i:s'),
            now()->addDays(10)->addMinutes(30)->format('Y-m-d H:i:s')
        );

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'canceled');
    }

    public function test_patient_list_includes_can_patient_cancel_flag(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $futureCancelable = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id);
        $pastAppointment = $this->createAppointment(
            $clinic->id,
            $patient->id,
            $dentist->id,
            $dentist->id,
            now()->subDay()->format('Y-m-d H:i:s'),
            now()->subDay()->addMinutes(30)->format('Y-m-d H:i:s')
        );

        Sanctum::actingAs($patient);

        $this->getJson('/api/pacient/appointments')
            ->assertOk()
            ->assertJsonPath('data.0.id', $pastAppointment->id)
            ->assertJsonPath('data.0.can_patient_cancel', false)
            ->assertJsonPath('data.1.id', $futureCancelable->id)
            ->assertJsonPath('data.1.can_patient_cancel', true);
    }

    private function makeClinicDentistAndPacient(): array
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Cancellation']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);

        return [$clinic, $dentist, $patient];
    }

    private function createAppointment(
        int $clinicId,
        int $patientId,
        int $dentistId,
        int $createdBy,
        ?string $startAt = null,
        ?string $endAt = null,
        string $status = 'scheduled'
    ): Appointment {
        $startAt ??= now()->addDays(2)->format('Y-m-d H:i:s');
        $endAt ??= now()->addDays(2)->addMinutes(30)->format('Y-m-d H:i:s');

        return Appointment::query()->create([
            'clinic_id' => $clinicId,
            'patient_user_id' => $patientId,
            'dentist_user_id' => $dentistId,
            'created_by' => $createdBy,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => $status,
        ]);
    }
}
