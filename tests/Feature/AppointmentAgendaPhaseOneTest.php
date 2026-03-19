<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DentistProfile;
use App\Models\Service;
use App\Models\Specialty;
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
        $service = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist);

        Sanctum::actingAs($dentist);

        $this->postJson('/api/appointments', [
            'patient_user_id' => $patient->id,
            'dentist_user_id' => $otherDentist->id,
            'service_id' => $service->id,
            'start_at' => '2026-05-10 10:00:00',
            'reason' => 'Consulta inicial',
        ])
            ->assertCreated()
            ->assertJsonPath('data.dentist_user_id', $dentist->id)
            ->assertJsonPath('data.end_at', '2026-05-10T10:30:00.000000Z');
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
        $service = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist);

        Sanctum::actingAs($superAdmin);

        $create = $this->postJson("/api/super/clinics/{$clinic->id}/appointments", [
            'patient_user_id' => $patient->id,
            'dentist_user_id' => $dentist->id,
            'service_id' => $service->id,
            'start_at' => '2026-04-20 09:00:00',
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
        $serviceA = $this->createServiceForClinicAndAssignToDentist($clinicA, User::factory()->create(['clinic_id' => $clinicA->id, 'role' => 'dentist']));

        Sanctum::actingAs($superAdmin);

        $this->postJson("/api/super/clinics/{$clinicA->id}/appointments", [
            'patient_user_id' => $patientB->id,
            'dentist_user_id' => $dentistB->id,
            'service_id' => $serviceA->id,
            'start_at' => '2026-04-20 09:00:00',
            'reason' => 'No debe crear',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_create_appointment_requires_service_and_computes_end_at_automatically(): void
    {
        [$clinic, $admin, $dentist, $patient] = $this->makeClinicActorDentistPatient('admin');
        $service = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist, 45);

        Sanctum::actingAs($admin);

        $this->postJson('/api/appointments', [
            'patient_user_id' => $patient->id,
            'dentist_user_id' => $dentist->id,
            'service_id' => $service->id,
            'start_at' => '2026-06-10 10:00:00',
            'reason' => 'Control',
        ])
            ->assertCreated()
            ->assertJsonPath('data.end_at', '2026-06-10T10:45:00.000000Z');
    }

    public function test_create_appointment_rejects_dentist_without_required_specialty(): void
    {
        [$clinic, $admin, $dentist, $patient] = $this->makeClinicActorDentistPatient('admin');
        $service = $this->createServiceForClinic($clinic, 60);

        Sanctum::actingAs($admin);

        $this->postJson('/api/appointments', [
            'patient_user_id' => $patient->id,
            'dentist_user_id' => $dentist->id,
            'service_id' => $service->id,
            'start_at' => '2026-06-10 10:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.dentist_user_id.0', 'El dentista seleccionado no cuenta con la especialidad requerida para este servicio.');
    }

    public function test_update_recalculates_end_at_when_service_changes(): void
    {
        [$clinic, $admin, $dentist, $patient] = $this->makeClinicActorDentistPatient('admin');
        $serviceShort = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist, 30);
        $serviceLong = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist, 90, 'Especialidad larga');

        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/appointments', [
            'patient_user_id' => $patient->id,
            'dentist_user_id' => $dentist->id,
            'service_id' => $serviceShort->id,
            'start_at' => '2026-06-10 09:00:00',
        ])->assertCreated();

        $appointmentId = (int) $create->json('data.id');

        $this->patchJson("/api/appointments/{$appointmentId}", [
            'service_id' => $serviceLong->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.end_at', '2026-06-10T10:30:00.000000Z');
    }

    public function test_update_recalculates_end_at_when_start_at_changes(): void
    {
        [$clinic, $admin, $dentist, $patient] = $this->makeClinicActorDentistPatient('admin');
        $service = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist, 60);

        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/appointments', [
            'patient_user_id' => $patient->id,
            'dentist_user_id' => $dentist->id,
            'service_id' => $service->id,
            'start_at' => '2026-06-10 09:00:00',
        ])->assertCreated();

        $appointmentId = (int) $create->json('data.id');

        $this->patchJson("/api/appointments/{$appointmentId}", [
            'start_at' => '2026-06-10 11:15:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.end_at', '2026-06-10T12:15:00.000000Z');
    }

    public function test_available_dentists_returns_only_compatible_and_truly_available_dentists(): void
    {
        [$clinic, $admin, $dentist] = $this->makeClinicActorDentistPatient('admin');
        $compatibleService = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist, 30);
        $notCompatibleDentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);

        Sanctum::actingAs($admin);

        $this->getJson('/api/appointments/available-dentists?service_id='.$compatibleService->id.'&start_at=2026-06-10 10:00:00')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dentist->id)
            ->assertJsonPath('meta.requested_start_at', '2026-06-10 10:00:00')
            ->assertJsonPath('meta.requested_end_at', '2026-06-10 10:30:00')
            ->assertJsonMissingPath('data.1.id');

        $this->assertNotSame($dentist->id, $notCompatibleDentist->id);
    }

    public function test_available_dentists_excludes_dentists_with_overlapping_appointments(): void
    {
        [$clinic, $admin, $dentistA, $patient] = $this->makeClinicActorDentistPatient('admin');
        $dentistB = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $service = $this->createServiceForClinicAndAssignToDentist($clinic, $dentistA, 60);
        $this->assignServiceSpecialtyToDentist($clinic, $dentistB, $service);

        $this->createAppointment(
            $clinic->id,
            $patient->id,
            $dentistA->id,
            $admin->id,
            '2026-06-10 10:15:00',
            '2026-06-10 10:45:00'
        );

        Sanctum::actingAs($admin);

        $this->getJson('/api/appointments/available-dentists?service_id='.$service->id.'&start_at=2026-06-10 10:00:00')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dentistB->id);
    }

    public function test_available_dentists_keeps_dentist_when_existing_appointment_ends_exactly_at_requested_start(): void
    {
        [$clinic, $admin, $dentist, $patient] = $this->makeClinicActorDentistPatient('admin');
        $service = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist, 60);

        $this->createAppointment(
            $clinic->id,
            $patient->id,
            $dentist->id,
            $admin->id,
            '2026-06-10 09:00:00',
            '2026-06-10 10:00:00'
        );

        Sanctum::actingAs($admin);

        $this->getJson('/api/appointments/available-dentists?service_id='.$service->id.'&start_at=2026-06-10 10:00:00')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dentist->id);
    }

    public function test_available_dentists_keeps_dentist_when_existing_appointment_starts_at_requested_end(): void
    {
        [$clinic, $admin, $dentist, $patient] = $this->makeClinicActorDentistPatient('admin');
        $service = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist, 60);

        $this->createAppointment(
            $clinic->id,
            $patient->id,
            $dentist->id,
            $admin->id,
            '2026-06-10 11:00:00',
            '2026-06-10 11:30:00'
        );

        Sanctum::actingAs($admin);

        $this->getJson('/api/appointments/available-dentists?service_id='.$service->id.'&start_at=2026-06-10 10:00:00')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dentist->id);
    }

    public function test_available_dentists_uses_service_duration_to_calculate_requested_end_at(): void
    {
        [$clinic, $admin, $dentist] = $this->makeClinicActorDentistPatient('admin');
        $service = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist, 45);

        Sanctum::actingAs($admin);

        $this->getJson('/api/appointments/available-dentists?service_id='.$service->id.'&start_at=2026-06-10 10:00:00')
            ->assertOk()
            ->assertJsonPath('meta.requested_end_at', '2026-06-10 10:45:00')
            ->assertJsonPath('meta.duration_minutes', 45);
    }

    public function test_available_dentists_respects_authenticated_clinic_scope(): void
    {
        [$clinicA, $adminA, $dentistA] = $this->makeClinicActorDentistPatient('admin');
        [$clinicB, , $dentistB] = $this->makeClinicActorDentistPatient('admin');
        $serviceA = $this->createServiceForClinicAndAssignToDentist($clinicA, $dentistA, 30);
        $this->createServiceForClinicAndAssignToDentist($clinicB, $dentistB, 30);

        Sanctum::actingAs($adminA);

        $this->getJson('/api/appointments/available-dentists?service_id='.$serviceA->id.'&start_at=2026-06-10 10:00:00')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dentistA->id)
            ->assertJsonMissing(['id' => $dentistB->id]);
    }

    public function test_super_admin_available_dentists_uses_clinic_from_url_scope(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'clinic_id' => null]);
        $clinicA = Clinic::query()->create(['name' => 'Clinic Scope A']);
        $clinicB = Clinic::query()->create(['name' => 'Clinic Scope B']);
        $dentistA = User::factory()->create(['clinic_id' => $clinicA->id, 'role' => 'dentist']);
        $dentistB = User::factory()->create(['clinic_id' => $clinicB->id, 'role' => 'dentist']);
        $serviceA = $this->createServiceForClinicAndAssignToDentist($clinicA, $dentistA, 30);
        $this->createServiceForClinicAndAssignToDentist($clinicB, $dentistB, 30);

        Sanctum::actingAs($superAdmin);

        $this->getJson("/api/super/clinics/{$clinicA->id}/appointments/available-dentists?service_id={$serviceA->id}&start_at=2026-06-10 10:00:00")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dentistA->id)
            ->assertJsonMissing(['id' => $dentistB->id]);
    }

    public function test_available_dentists_can_ignore_current_appointment_when_exclude_appointment_id_is_sent(): void
    {
        [$clinic, $admin, $dentist, $patient] = $this->makeClinicActorDentistPatient('admin');
        $service = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist, 60);

        $appointment = Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'patient_user_id' => $patient->id,
            'dentist_user_id' => $dentist->id,
            'service_id' => $service->id,
            'created_by' => $admin->id,
            'start_at' => '2026-06-10 10:00:00',
            'end_at' => '2026-06-10 11:00:00',
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/appointments/available-dentists?service_id='.$service->id.'&start_at=2026-06-10 10:00:00')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/appointments/available-dentists?service_id='.$service->id.'&start_at=2026-06-10 10:00:00&exclude_appointment_id='.$appointment->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dentist->id);
    }

    public function test_create_and_update_keep_rejecting_overlaps_with_same_rule_used_in_available_dentists(): void
    {
        [$clinic, $admin, $dentist, $patient] = $this->makeClinicActorDentistPatient('admin');
        $patientTwo = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $service = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist, 60);

        Sanctum::actingAs($admin);

        $existing = $this->postJson('/api/appointments', [
            'patient_user_id' => $patient->id,
            'dentist_user_id' => $dentist->id,
            'service_id' => $service->id,
            'start_at' => '2026-06-10 10:00:00',
        ])->assertCreated();

        $this->postJson('/api/appointments', [
            'patient_user_id' => $patientTwo->id,
            'dentist_user_id' => $dentist->id,
            'service_id' => $service->id,
            'start_at' => '2026-06-10 10:30:00',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.appointment.0', 'El dentista ya tiene una cita en ese horario.');

        $existingId = (int) $existing->json('data.id');
        $other = $this->postJson('/api/appointments', [
            'patient_user_id' => $patientTwo->id,
            'dentist_user_id' => $dentist->id,
            'service_id' => $service->id,
            'start_at' => '2026-06-10 12:00:00',
        ])->assertCreated();
        $otherId = (int) $other->json('data.id');

        $this->patchJson("/api/appointments/{$otherId}", [
            'start_at' => '2026-06-10 10:30:00',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.appointment.0', 'El dentista ya tiene una cita en ese horario.');

        $this->assertNotSame($existingId, $otherId);
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

    private function createServiceForClinicAndAssignToDentist(
        Clinic $clinic,
        User $dentist,
        int $durationMinutes = 30,
        string $specialtyName = 'Ortodoncia'
    ): Service {
        $service = $this->createServiceForClinic($clinic, $durationMinutes, $specialtyName);
        $profile = DentistProfile::query()->firstOrCreate(
            ['user_id' => $dentist->id],
            ['clinic_id' => $clinic->id]
        );
        $profile->specialties()->syncWithoutDetaching([$service->specialty_id]);

        return $service;
    }

    private function assignServiceSpecialtyToDentist(Clinic $clinic, User $dentist, Service $service): void
    {
        $profile = DentistProfile::query()->firstOrCreate(
            ['user_id' => $dentist->id],
            ['clinic_id' => $clinic->id]
        );

        $profile->specialties()->syncWithoutDetaching([$service->specialty_id]);
    }

    private function createServiceForClinic(
        Clinic $clinic,
        int $durationMinutes = 30,
        string $specialtyName = 'Ortodoncia'
    ): Service {
        $specialty = Specialty::query()->firstOrCreate(['name' => $specialtyName]);

        return Service::query()->create([
            'clinic_id' => $clinic->id,
            'specialty_id' => $specialty->id,
            'name' => 'Servicio '.$durationMinutes,
            'duration_minutes' => $durationMinutes,
            'price' => 500,
            'status' => true,
        ]);
    }
}
