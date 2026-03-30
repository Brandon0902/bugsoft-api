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

class PacientAppointmentBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pacient_can_list_services(): void
    {
        [$clinic, $pacient] = $this->makeClinicAndPacient();
        $activeService = $this->createServiceForClinic($clinic, 45, 'Ortodoncia', true);
        $this->createServiceForClinic($clinic, 30, 'Endodoncia', false);
        $otherClinic = Clinic::query()->create(['name' => 'Otra clínica']);
        $this->createServiceForClinic($otherClinic, 30, 'Cirugía', true);

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeService->id)
            ->assertJsonPath('data.0.specialty.id', $activeService->specialty_id);
    }

    public function test_pacient_can_list_compatible_dentists_for_service(): void
    {
        [$clinic, $pacient] = $this->makeClinicAndPacient();
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist', 'status' => true]);
        $service = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist, 30);
        $otherSpecialtyDentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist', 'status' => true]);
        $inactiveDentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist', 'status' => false]);
        $otherClinicDentist = User::factory()->create(['clinic_id' => Clinic::query()->create(['name' => 'Fuera'])->id, 'role' => 'dentist', 'status' => true]);

        $this->createDentistProfile($clinic, $otherSpecialtyDentist, Specialty::query()->firstOrCreate(['name' => 'Otra']));
        $this->createDentistProfile($clinic, $inactiveDentist, Specialty::query()->findOrFail($service->specialty_id));
        $this->createDentistProfile(Clinic::query()->findOrFail($otherClinicDentist->clinic_id), $otherClinicDentist, Specialty::query()->findOrFail($service->specialty_id));

        Sanctum::actingAs($pacient);

        $this->getJson("/api/pacient/services/{$service->id}/dentists")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dentist->id)
            ->assertJsonPath('data.0.dentist_profile.user_id', $dentist->id)
            ->assertJsonPath('data.0.dentist_profile.specialties.0.id', $service->specialty_id);
    }

    public function test_pacient_can_create_own_appointment_with_compatible_service_and_dentist(): void
    {
        [$clinic, $pacient] = $this->makeClinicAndPacient();
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist', 'status' => true]);
        $service = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist, 45);

        Sanctum::actingAs($pacient);

        $this->postJson('/api/pacient/appointments', [
            'service_id' => $service->id,
            'dentist_user_id' => $dentist->id,
            'start_at' => '2026-04-10 10:00:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.patient_user_id', $pacient->id)
            ->assertJsonPath('data.dentist_user_id', $dentist->id)
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.end_at', '2026-04-10T10:45:00.000000Z')
            ->assertJsonPath('data.service.id', $service->id)
            ->assertJsonPath('data.dentist.id', $dentist->id);
    }

    public function test_pacient_payload_patient_user_id_is_ignored_and_appointment_is_created_for_authenticated_user(): void
    {
        [$clinic, $pacient] = $this->makeClinicAndPacient();
        $otherPacient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist', 'status' => true]);
        $service = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist, 30);

        Sanctum::actingAs($pacient);

        $this->postJson('/api/pacient/appointments', [
            'patient_user_id' => $otherPacient->id,
            'service_id' => $service->id,
            'dentist_user_id' => $dentist->id,
            'start_at' => '2026-04-10 12:00:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.patient_user_id', $pacient->id);
    }

    public function test_creation_fails_if_dentist_lacks_required_specialty(): void
    {
        [$clinic, $pacient] = $this->makeClinicAndPacient();
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist', 'status' => true]);
        $service = $this->createServiceForClinic($clinic, 30);

        Sanctum::actingAs($pacient);

        $this->postJson('/api/pacient/appointments', [
            'service_id' => $service->id,
            'dentist_user_id' => $dentist->id,
            'start_at' => '2026-04-10 10:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.dentist_user_id.0', 'El dentista seleccionado no cuenta con la especialidad requerida para este servicio.');
    }

    public function test_creation_fails_if_service_and_dentist_are_from_different_clinics(): void
    {
        [$clinic, $pacient] = $this->makeClinicAndPacient();
        $otherClinic = Clinic::query()->create(['name' => 'Clínica externa']);
        $dentist = User::factory()->create(['clinic_id' => $otherClinic->id, 'role' => 'dentist', 'status' => true]);
        $service = $this->createServiceForClinic($clinic, 30);

        Sanctum::actingAs($pacient);

        $this->postJson('/api/pacient/appointments', [
            'service_id' => $service->id,
            'dentist_user_id' => $dentist->id,
            'start_at' => '2026-04-10 10:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.dentist_user_id.0', 'El dentista debe pertenecer a la clínica, tener rol dentist y estar activo.');
    }

    public function test_creation_fails_when_dentist_has_overlapping_appointment(): void
    {
        [$clinic, $pacient] = $this->makeClinicAndPacient();
        $otherPacient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist', 'status' => true]);
        $service = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist, 60);

        Appointment::query()->create([
            'clinic_id' => $clinic->id,
            'patient_user_id' => $otherPacient->id,
            'dentist_user_id' => $dentist->id,
            'service_id' => $service->id,
            'created_by' => $otherPacient->id,
            'start_at' => '2026-04-10 10:30:00',
            'end_at' => '2026-04-10 11:00:00',
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($pacient);

        $this->postJson('/api/pacient/appointments', [
            'service_id' => $service->id,
            'dentist_user_id' => $dentist->id,
            'start_at' => '2026-04-10 10:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.appointment.0', 'El dentista ya tiene una cita en el horario seleccionado.');
    }

    public function test_creation_fails_if_start_at_is_in_the_past(): void
    {
        [$clinic, $pacient] = $this->makeClinicAndPacient();
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist', 'status' => true]);
        $service = $this->createServiceForClinicAndAssignToDentist($clinic, $dentist, 30);

        Sanctum::actingAs($pacient);

        $this->postJson('/api/pacient/appointments', [
            'service_id' => $service->id,
            'dentist_user_id' => $dentist->id,
            'start_at' => '2026-03-01 10:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_at']);
    }

    public function test_other_roles_cannot_use_pacient_booking_endpoints(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Roles']);
        $service = $this->createServiceForClinic($clinic, 30);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist', 'status' => true]);
        $this->createDentistProfile($clinic, $dentist, Specialty::query()->findOrFail($service->specialty_id));
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);
        $receptionist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'receptionist']);

        foreach ([$admin, $dentist, $receptionist] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/pacient/services')->assertForbidden();
            $this->getJson("/api/pacient/services/{$service->id}/dentists")->assertForbidden();
            $this->postJson('/api/pacient/appointments', [
                'service_id' => $service->id,
                'dentist_user_id' => $dentist->id,
                'start_at' => '2026-04-10 10:00:00',
            ])->assertForbidden();
        }
    }

    private function makeClinicAndPacient(): array
    {
        $clinic = Clinic::query()->create(['name' => 'Clínica paciente']);
        $pacient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);

        return [$clinic, $pacient];
    }

    private function createServiceForClinicAndAssignToDentist(
        Clinic $clinic,
        User $dentist,
        int $durationMinutes = 30,
        string $specialtyName = 'Ortodoncia'
    ): Service {
        $service = $this->createServiceForClinic($clinic, $durationMinutes, $specialtyName);
        $this->createDentistProfile($clinic, $dentist, Specialty::query()->findOrFail($service->specialty_id));

        return $service;
    }

    private function createDentistProfile(Clinic $clinic, User $dentist, Specialty $specialty): void
    {
        $profile = DentistProfile::query()->firstOrCreate(
            ['user_id' => $dentist->id],
            ['clinic_id' => $clinic->id]
        );

        $profile->specialties()->syncWithoutDetaching([$specialty->id]);
    }

    private function createServiceForClinic(
        Clinic $clinic,
        int $durationMinutes = 30,
        string $specialtyName = 'Ortodoncia',
        bool $status = true
    ): Service {
        $specialty = Specialty::query()->firstOrCreate(['name' => $specialtyName]);

        return Service::query()->create([
            'clinic_id' => $clinic->id,
            'specialty_id' => $specialty->id,
            'name' => 'Servicio '.$durationMinutes,
            'duration_minutes' => $durationMinutes,
            'price' => 500,
            'status' => $status,
        ]);
    }
}
