<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DentistPatientReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_dentist_can_list_patients_from_own_clinic(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Dentist Patients']);
        $otherClinic = Clinic::query()->create(['name' => 'Clinic Other']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        PatientProfile::query()->create(['user_id' => $patient->id, 'clinic_id' => $clinic->id]);

        $otherClinicPatient = User::factory()->create(['clinic_id' => $otherClinic->id, 'role' => 'pacient']);
        PatientProfile::query()->create(['user_id' => $otherClinicPatient->id, 'clinic_id' => $otherClinic->id]);

        Sanctum::actingAs($dentist);

        $this->getJson('/api/dentist/patients')
            ->assertOk()
            ->assertJsonFragment(['id' => $patient->id])
            ->assertJsonMissing(['id' => $otherClinicPatient->id]);
    }

    public function test_dentist_can_show_patient_from_own_clinic(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Dentist Show Patient']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $patient = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => 'pacient',
            'email' => 'patient.show@example.com',
        ]);

        PatientProfile::query()->create([
            'user_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'address' => 'Main St 123',
        ]);

        Sanctum::actingAs($dentist);

        $this->getJson("/api/dentist/patients/{$patient->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $patient->id)
            ->assertJsonPath('data.email', 'patient.show@example.com')
            ->assertJsonPath('data.patient_profile.address', 'Main St 123');
    }

    public function test_dentist_cannot_show_patient_from_other_clinic(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Dentist Scope']);
        $otherClinic = Clinic::query()->create(['name' => 'Clinic Outside Scope']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $otherPatient = User::factory()->create(['clinic_id' => $otherClinic->id, 'role' => 'pacient']);

        Sanctum::actingAs($dentist);

        $this->getJson("/api/dentist/patients/{$otherPatient->id}")
            ->assertNotFound();
    }

    public function test_dentist_cannot_create_patient(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Dentist No Write']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);

        Sanctum::actingAs($dentist);

        $this->postJson('/api/admin/patients', [
            'name' => 'Nuevo paciente',
            'email' => 'new.patient@example.com',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden');
    }

    public function test_dentist_cannot_update_patient(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Dentist No Update']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);

        Sanctum::actingAs($dentist);

        $this->patchJson("/api/admin/patients/{$patient->id}", [
            'name' => 'Intento no autorizado',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden');
    }

    public function test_dentist_cannot_delete_patient(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Dentist No Delete']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);

        Sanctum::actingAs($dentist);

        $this->deleteJson("/api/admin/patients/{$patient->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden');
    }
}
