<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PacientAppointmentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_show_own_appointment_detail(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment(
            $clinic->id,
            $patient->id,
            $dentist->id,
            $dentist->id,
            now()->addDays(2)->format('Y-m-d H:i:s'),
            now()->addDays(2)->addMinutes(30)->format('Y-m-d H:i:s')
        );

        Sanctum::actingAs($patient);

        $this->getJson("/api/pacient/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Cita del paciente obtenida.')
            ->assertJsonPath('data.id', $appointment->id)
            ->assertJsonPath('data.dentist.id', $dentist->id)
            ->assertJsonPath('data.can_patient_confirm', true)
            ->assertJsonPath('data.confirmation_window_open', true);
    }

    public function test_patient_cannot_show_other_patient_appointment(): void
    {
        [$clinic, $dentist] = $this->makeClinicDentistAndPacient();
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $otherPatient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $appointment = $this->createAppointment($clinic->id, $otherPatient->id, $dentist->id, $dentist->id);

        Sanctum::actingAs($patient);

        $this->getJson("/api/pacient/appointments/{$appointment->id}")
            ->assertNotFound();
    }

    public function test_patient_can_confirm_own_appointment_with_yes(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment(
            $clinic->id,
            $patient->id,
            $dentist->id,
            $dentist->id,
            now()->addDays(1)->format('Y-m-d H:i:s'),
            now()->addDays(1)->addMinutes(30)->format('Y-m-d H:i:s')
        );

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/confirmation", [
            'response' => 'yes',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Cita confirmada por el paciente.')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.can_patient_confirm', false);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_patient_can_cancel_own_appointment_with_no(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment(
            $clinic->id,
            $patient->id,
            $dentist->id,
            $dentist->id,
            now()->addDays(1)->format('Y-m-d H:i:s'),
            now()->addDays(1)->addMinutes(30)->format('Y-m-d H:i:s')
        );

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/confirmation", [
            'response' => 'no',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Cita cancelada por el paciente.')
            ->assertJsonPath('data.status', 'canceled')
            ->assertJsonPath('data.can_patient_confirm', false);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'canceled',
        ]);
    }

    public function test_patient_cannot_confirm_other_patient_appointment(): void
    {
        [$clinic, $dentist] = $this->makeClinicDentistAndPacient();
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $otherPatient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $appointment = $this->createAppointment(
            $clinic->id,
            $otherPatient->id,
            $dentist->id,
            $dentist->id,
            now()->addDays(1)->format('Y-m-d H:i:s'),
            now()->addDays(1)->addMinutes(30)->format('Y-m-d H:i:s')
        );

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/confirmation", [
            'response' => 'yes',
        ])->assertNotFound();
    }

    public function test_patient_cannot_respond_with_invalid_value(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment(
            $clinic->id,
            $patient->id,
            $dentist->id,
            $dentist->id,
            now()->addDays(1)->format('Y-m-d H:i:s'),
            now()->addDays(1)->addMinutes(30)->format('Y-m-d H:i:s')
        );

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/confirmation", [
            'response' => 'maybe',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.response.0', 'The selected response is invalid.');
    }

    public function test_patient_cannot_respond_if_appointment_already_confirmed(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment(
            $clinic->id,
            $patient->id,
            $dentist->id,
            $dentist->id,
            now()->addDays(1)->format('Y-m-d H:i:s'),
            now()->addDays(1)->addMinutes(30)->format('Y-m-d H:i:s'),
            'confirmed'
        );

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/confirmation", [
            'response' => 'yes',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La cita ya no puede responderse.');
    }

    public function test_patient_cannot_respond_if_appointment_already_canceled(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment(
            $clinic->id,
            $patient->id,
            $dentist->id,
            $dentist->id,
            now()->addDays(1)->format('Y-m-d H:i:s'),
            now()->addDays(1)->addMinutes(30)->format('Y-m-d H:i:s'),
            'canceled'
        );

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/confirmation", [
            'response' => 'no',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La cita ya no puede responderse.');
    }

    public function test_patient_cannot_respond_before_confirmation_window_opens(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment(
            $clinic->id,
            $patient->id,
            $dentist->id,
            $dentist->id,
            now()->addDays(5)->format('Y-m-d H:i:s'),
            now()->addDays(5)->addMinutes(30)->format('Y-m-d H:i:s')
        );

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/confirmation", [
            'response' => 'yes',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La cita aún no está disponible para confirmación.');
    }

    public function test_patient_cannot_respond_if_appointment_is_in_past(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment(
            $clinic->id,
            $patient->id,
            $dentist->id,
            $dentist->id,
            now()->subHour()->format('Y-m-d H:i:s'),
            now()->subMinutes(30)->format('Y-m-d H:i:s')
        );

        Sanctum::actingAs($patient);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/confirmation", [
            'response' => 'yes',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La cita ya no puede responderse porque ya ocurrió.');
    }

    public function test_dentist_cannot_use_patient_confirmation_endpoint(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id);

        Sanctum::actingAs($dentist);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/confirmation", [
            'response' => 'yes',
        ])->assertForbidden();
    }

    public function test_receptionist_cannot_use_patient_confirmation_endpoint(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Confirmation']);
        $receptionist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'receptionist']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $receptionist->id);

        Sanctum::actingAs($receptionist);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/confirmation", [
            'response' => 'yes',
        ])->assertForbidden();
    }

    public function test_admin_cannot_use_patient_confirmation_endpoint(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Confirmation']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $admin->id);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/pacient/appointments/{$appointment->id}/confirmation", [
            'response' => 'yes',
        ])->assertForbidden();
    }

    private function makeClinicDentistAndPacient(): array
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Confirmation']);
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
