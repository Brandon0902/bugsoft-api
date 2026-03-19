<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\DentistProfile;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpecialtyServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_super_admin_have_full_specialty_crud_access(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $clinic = Clinic::query()->create(['name' => 'Clinic One']);
        $admin = User::factory()->create(['role' => 'admin', 'clinic_id' => $clinic->id]);
        $existing = Specialty::query()->create([
            'name' => 'Implantología',
            'description' => 'Tratamientos de implantes',
            'status' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/specialties')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $existing->id);

        $createResponse = $this->postJson('/api/specialties', [
            'name' => 'Ortodoncia',
            'description' => 'Especialidad dental',
            'status' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ortodoncia');

        $specialtyId = (int) $createResponse->json('data.id');

        $this->getJson("/api/specialties/{$specialtyId}")
            ->assertOk()
            ->assertJsonPath('data.id', $specialtyId);

        $this->patchJson("/api/specialties/{$specialtyId}", [
            'description' => 'Descripción actualizada',
        ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Descripción actualizada');

        $this->deleteJson("/api/specialties/{$specialtyId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('specialties', ['id' => $specialtyId]);

        Sanctum::actingAs($superAdmin);

        $this->postJson('/api/specialties', [
            'name' => 'Endodoncia',
            'description' => 'Especialidad de conductos',
            'status' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Endodoncia');
    }

    public function test_receptionist_dentist_and_pacient_cannot_access_specialties_crud(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Roles']);
        $receptionist = User::factory()->create(['role' => 'receptionist', 'clinic_id' => $clinic->id]);
        $dentist = User::factory()->create(['role' => 'dentist', 'clinic_id' => $clinic->id]);
        $pacient = User::factory()->create(['role' => 'pacient', 'clinic_id' => $clinic->id]);
        $specialty = Specialty::query()->create([
            'name' => 'Periodoncia',
            'description' => 'Encías',
            'status' => true,
        ]);

        foreach ([$receptionist, $dentist, $pacient] as $user) {
            Sanctum::actingAs($user);

            $this->getJson('/api/specialties')
                ->assertForbidden()
                ->assertJsonPath('message', 'Forbidden');

            $this->postJson('/api/specialties', [
                'name' => "Especialidad {$user->id}",
                'description' => 'No autorizado',
                'status' => true,
            ])
                ->assertForbidden()
                ->assertJsonPath('message', 'Forbidden');

            $this->getJson("/api/specialties/{$specialty->id}")
                ->assertForbidden()
                ->assertJsonPath('message', 'Forbidden');

            $this->patchJson("/api/specialties/{$specialty->id}", [
                'description' => 'No autorizado',
            ])
                ->assertForbidden()
                ->assertJsonPath('message', 'Forbidden');

            $this->deleteJson("/api/specialties/{$specialty->id}")
                ->assertForbidden()
                ->assertJsonPath('message', 'Forbidden');
        }
    }

    public function test_admin_can_create_service_in_own_clinic_and_cannot_read_other_clinic_service(): void
    {
        $specialty = Specialty::query()->create(['name' => 'Endodoncia']);
        $clinicA = Clinic::query()->create(['name' => 'Clinic A']);
        $clinicB = Clinic::query()->create(['name' => 'Clinic B']);

        $adminA = User::factory()->create(['role' => 'admin', 'clinic_id' => $clinicA->id]);
        $adminB = User::factory()->create(['role' => 'admin', 'clinic_id' => $clinicB->id]);

        Sanctum::actingAs($adminA);

        $response = $this->postJson('/api/services', [
            'specialty_id' => $specialty->id,
            'name' => 'Tratamiento de conducto',
            'duration_minutes' => 60,
            'price' => 1500,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Tratamiento de conducto')
            ->assertJsonPath('data.clinic_id', $clinicA->id);

        $serviceId = (int) $response->json('data.id');

        Sanctum::actingAs($adminB);

        $this->getJson("/api/services/{$serviceId}")->assertNotFound();
    }

    public function test_admin_can_assign_specialty_ids_on_dentist_create_and_update(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Dentists']);
        $admin = User::factory()->create(['role' => 'admin', 'clinic_id' => $clinic->id]);

        $orthodontics = Specialty::query()->create(['name' => 'Ortodoncia']);
        $periodontics = Specialty::query()->create(['name' => 'Periodoncia']);

        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/admin/users', [
            'name' => 'Dra. Sync',
            'email' => 'dra.sync@example.com',
            'password' => 'password123',
            'role' => 'dentist',
            'specialty_ids' => [$orthodontics->id],
        ])
            ->assertCreated();

        $dentistId = (int) $createResponse->json('data.id');
        $profileId = (int) DentistProfile::query()->where('user_id', $dentistId)->value('id');

        $this->assertDatabaseHas('dentist_specialty', [
            'dentist_profile_id' => $profileId,
            'specialty_id' => $orthodontics->id,
        ]);

        $this->patchJson("/api/admin/users/{$dentistId}", [
            'specialty_ids' => [$periodontics->id],
        ])
            ->assertOk();

        $this->assertDatabaseMissing('dentist_specialty', [
            'dentist_profile_id' => $profileId,
            'specialty_id' => $orthodontics->id,
        ]);

        $this->assertDatabaseHas('dentist_specialty', [
            'dentist_profile_id' => $profileId,
            'specialty_id' => $periodontics->id,
        ]);
    }
}
