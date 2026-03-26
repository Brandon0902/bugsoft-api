<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\DentistProfile;
use App\Models\Service;
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

    public function test_dentist_can_list_services(): void
    {
        $specialty = Specialty::query()->create(['name' => 'Ortodoncia']);
        $clinic = Clinic::query()->create(['name' => 'Clinic Dentist Services']);
        $dentist = User::factory()->create(['role' => 'dentist', 'clinic_id' => $clinic->id]);
        $visibleService = Service::query()->create([
            'clinic_id' => $clinic->id,
            'specialty_id' => $specialty->id,
            'name' => 'Consulta general',
            'duration_minutes' => 30,
            'price' => 500,
            'status' => true,
        ]);

        Service::query()->create([
            'clinic_id' => Clinic::query()->create(['name' => 'Other Clinic'])->id,
            'specialty_id' => $specialty->id,
            'name' => 'Servicio ajeno',
            'duration_minutes' => 45,
            'price' => 700,
            'status' => true,
        ]);

        Sanctum::actingAs($dentist);

        $this->getJson('/api/services')
            ->assertOk()
            ->assertJsonPath('data.0.id', $visibleService->id)
            ->assertJsonMissing(['name' => 'Servicio ajeno']);
    }

    public function test_dentist_can_show_service(): void
    {
        $specialty = Specialty::query()->create(['name' => 'Prostodoncia']);
        $clinic = Clinic::query()->create(['name' => 'Clinic Dentist Show']);
        $dentist = User::factory()->create(['role' => 'dentist', 'clinic_id' => $clinic->id]);
        $service = Service::query()->create([
            'clinic_id' => $clinic->id,
            'specialty_id' => $specialty->id,
            'name' => 'Resina dental',
            'duration_minutes' => 40,
            'price' => 900,
            'status' => true,
        ]);

        Sanctum::actingAs($dentist);

        $this->getJson("/api/services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $service->id)
            ->assertJsonPath('data.name', 'Resina dental');
    }

    public function test_dentist_cannot_create_service(): void
    {
        $specialty = Specialty::query()->create(['name' => 'CirugÃ­a oral']);
        $clinic = Clinic::query()->create(['name' => 'Clinic Dentist Write']);
        $dentist = User::factory()->create(['role' => 'dentist', 'clinic_id' => $clinic->id]);

        Sanctum::actingAs($dentist);

        $this->postJson('/api/services', [
            'specialty_id' => $specialty->id,
            'name' => 'No autorizado',
            'duration_minutes' => 60,
            'price' => 1200,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden');
    }

    public function test_dentist_cannot_update_service(): void
    {
        $specialty = Specialty::query()->create(['name' => 'OdontopediatrÃ­a']);
        $clinic = Clinic::query()->create(['name' => 'Clinic Dentist Update']);
        $dentist = User::factory()->create(['role' => 'dentist', 'clinic_id' => $clinic->id]);
        $service = Service::query()->create([
            'clinic_id' => $clinic->id,
            'specialty_id' => $specialty->id,
            'name' => 'Limpieza',
            'duration_minutes' => 30,
            'price' => 400,
            'status' => true,
        ]);

        Sanctum::actingAs($dentist);

        $this->patchJson("/api/services/{$service->id}", [
            'name' => 'No debe editar',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden');
    }

    public function test_dentist_cannot_delete_service(): void
    {
        $specialty = Specialty::query()->create(['name' => 'RehabilitaciÃ³n oral']);
        $clinic = Clinic::query()->create(['name' => 'Clinic Dentist Delete']);
        $dentist = User::factory()->create(['role' => 'dentist', 'clinic_id' => $clinic->id]);
        $service = Service::query()->create([
            'clinic_id' => $clinic->id,
            'specialty_id' => $specialty->id,
            'name' => 'PrÃ³tesis',
            'duration_minutes' => 90,
            'price' => 3500,
            'status' => true,
        ]);

        Sanctum::actingAs($dentist);

        $this->deleteJson("/api/services/{$service->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden');
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
