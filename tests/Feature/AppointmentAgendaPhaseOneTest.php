<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentAgendaPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_receptionist_keep_listing_behavior_for_own_clinic(): void
    {
        [$clinic, $admin, $dentist, $patient] = $this->makeClinicActorDentistPatient('admin');
        [$sameClinic, $receptionist] = $this->makeClinicActorDentistPatient('receptionist', $clinic);
        $this->assertSame($clinic->id, $sameClinic->id);

        Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'patient_user_id' => $patient->id,
            'dentist_user_id' => $dentist->id,
            'created_by' => $admin->id,
            'start_at' => '2026-04-10 09:00:00',
            'end_at' => '2026-04-10 09:30:00',
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/appointments?date=2026-04-10&dentist_user_id='.$dentist->id.'&patient_user_id='.$patient->id.'&status=scheduled')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Sanctum::actingAs($receptionist);
        $this->getJson('/api/appointments?from=2026-04-10 00:00:00&to=2026-04-10 23:59:59&status=scheduled')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_dentist_can_create_appointment_for_self_even_if_payload_has_other_dentist(): void
    {
        [$clinic, $dentist, , $patient] = $this->makeClinicActorDentistPatient('dentist');
        $otherDentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);

        Sanctum::actingAs($dentist);

        $this->postJson('/api/appointments', [
            'patient_user_id' => $patient->id,
            'dentist_user_id' => $otherDentist->id,
            'start_at' => '2026-05-10 10:00:00',
            'end_at' => '2026-05-10 10:30:00',
            'reason' => 'Consulta inicial',
        ])
            ->assertCreated()
            ->assertJsonPath('data.dentist_user_id', $dentist->id);
    }

    public function test_dentist_sees_only_own_appointments(): void
    {
        [$clinic, $dentist, , $patient] = $this->makeClinicActorDentistPatient('dentist');
        $otherDentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $otherPatient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);

        $own = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id, '2026-04-14 10:00:00', '2026-04-14 10:30:00');
        $this->createAppointment($clinic->id, $otherPatient->id, $otherDentist->id, $otherDentist->id, '2026-04-14 11:00:00', '2026-04-14 11:30:00');

        Sanctum::actingAs($dentist);

        $this->getJson('/api/appointments?dentist_user_id='.$otherDentist->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.dentist_user_id', $dentist->id);
    }

    public function test_dentist_cannot_see_or_edit_other_dentist_appointment(): void
    {
        [$clinic, $dentist, , $patient] = $this->makeClinicActorDentistPatient('dentist');
        $otherDentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $otherPatient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $foreignAppointment = $this->createAppointment($clinic->id, $otherPatient->id, $otherDentist->id, $otherDentist->id);

        Sanctum::actingAs($dentist);

        $this->getJson("/api/appointments/{$foreignAppointment->id}")->assertNotFound();
        $this->patchJson("/api/appointments/{$foreignAppointment->id}", ['reason' => 'No'])->assertNotFound();
    }

    public function test_dentist_cannot_reassign_dentist_on_update_but_can_change_status_on_own_appointment(): void
    {
        [$clinic, $dentist, , $patient] = $this->makeClinicActorDentistPatient('dentist');
        $otherDentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id);

        Sanctum::actingAs($dentist);

        $this->patchJson("/api/appointments/{$appointment->id}", [
            'dentist_user_id' => $otherDentist->id,
            'reason' => 'Actualizada por dentista',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.dentist_user_id.0', 'El campo dentist user id está prohibido.');

        $this->patchJson("/api/appointments/{$appointment->id}", [
            'reason' => 'Actualizada por dentista',
        ])
            ->assertOk()
            ->assertJsonPath('data.dentist_user_id', $dentist->id)
            ->assertJsonPath('data.reason', 'Actualizada por dentista');

        $this->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_super_admin_can_list_create_show_update_and_update_status_for_any_clinic_scope(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'clinic_id' => null]);
        $clinic = Clinic::query()->create(['name' => 'Clinic Super']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);

        Sanctum::actingAs($superAdmin);

        $create = $this->postJson("/api/super/clinics/{$clinic->id}/appointments", [
            'patient_user_id' => $patient->id,
            'dentist_user_id' => $dentist->id,
            'start_at' => '2026-04-20 09:00:00',
            'end_at' => '2026-04-20 09:30:00',
            'reason' => 'Creada por super',
        ])
            ->assertCreated();

        $appointmentId = $create->json('data.id');

        $this->getJson("/api/super/clinics/{$clinic->id}/appointments?status=scheduled")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/super/clinics/{$clinic->id}/appointments/{$appointmentId}")
            ->assertOk()
            ->assertJsonPath('data.id', $appointmentId);

        $this->patchJson("/api/super/clinics/{$clinic->id}/appointments/{$appointmentId}", [
            'reason' => 'Super editó',
            'status' => 'confirmed',
        ])
            ->assertOk()
            ->assertJsonPath('data.reason', 'Super editó')
            ->assertJsonPath('data.status', 'confirmed');

        $this->patchJson("/api/super/clinics/{$clinic->id}/appointments/{$appointmentId}/status", [
            'status' => 'completed',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_super_admin_gets_404_when_appointment_does_not_belong_to_clinic_from_url(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'clinic_id' => null]);
        $clinicA = Clinic::query()->create(['name' => 'Clinic A']);
        $clinicB = Clinic::query()->create(['name' => 'Clinic B']);
        $dentistB = User::factory()->create(['clinic_id' => $clinicB->id, 'role' => 'dentist']);
        $patientB = User::factory()->create(['clinic_id' => $clinicB->id, 'role' => 'pacient']);
        $appointmentB = $this->createAppointment($clinicB->id, $patientB->id, $dentistB->id, $dentistB->id);

        Sanctum::actingAs($superAdmin);

        $this->getJson("/api/super/clinics/{$clinicA->id}/appointments/{$appointmentB->id}")
            ->assertNotFound();
    }

    public function test_super_admin_cannot_create_appointment_with_users_outside_url_clinic_scope(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'clinic_id' => null]);
        $clinicA = Clinic::query()->create(['name' => 'Clinic A']);
        $clinicB = Clinic::query()->create(['name' => 'Clinic B']);
        $dentistB = User::factory()->create(['clinic_id' => $clinicB->id, 'role' => 'dentist']);
        $patientB = User::factory()->create(['clinic_id' => $clinicB->id, 'role' => 'pacient']);

        Sanctum::actingAs($superAdmin);

        $this->postJson("/api/super/clinics/{$clinicA->id}/appointments", [
            'patient_user_id' => $patientB->id,
            'dentist_user_id' => $dentistB->id,
            'start_at' => '2026-04-20 09:00:00',
            'end_at' => '2026-04-20 09:30:00',
            'reason' => 'No debe crear',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_pacient_cannot_access_general_appointment_endpoints(): void
    {
        [$clinic, $admin, $dentist, $patient] = $this->makeClinicActorDentistPatient('admin');
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $admin->id);

        Sanctum::actingAs($patient);

        $this->getJson('/api/appointments')->assertForbidden();
        $this->getJson("/api/appointments/{$appointment->id}")->assertForbidden();
        $this->patchJson("/api/appointments/{$appointment->id}", ['reason' => 'No'])->assertForbidden();
    }

    private function makeClinicActorDentistPatient(string $actorRole = 'admin', ?Clinic $clinic = null): array
    {
        $clinic ??= Clinic::query()->create(['name' => 'Clinic Agenda']);
        $actor = User::factory()->create(['clinic_id' => $clinic->id, 'role' => $actorRole]);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);

        return [$clinic, $actor, $dentist, $patient];
    }

    private function createAppointment(
        int $clinicId,
        int $patientId,
        int $dentistId,
        int $createdBy,
        string $startAt = '2026-04-10 10:00:00',
        string $endAt = '2026-04-10 10:30:00'
    ): Appointment {
        return Appointment::query()->create([
            'clinic_id' => $clinicId,
            'patient_user_id' => $patientId,
            'dentist_user_id' => $dentistId,
            'created_by' => $createdBy,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => 'scheduled',
        ]);
    }
}
