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

class PacientClinicInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_pacient_gets_200_for_clinic_info(): void
    {
        [$clinic, $pacient] = $this->makeClinicData();

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/clinic')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Información de la clínica obtenida.')
            ->assertJsonPath('data.clinic.id', $clinic->id);
    }

    public function test_response_includes_clinic_services_and_dentists(): void
    {
        [$clinic, $pacient, $service, $dentist] = $this->makeClinicData();

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/clinic')
            ->assertOk()
            ->assertJsonPath('data.clinic.name', 'Clínica Centro')
            ->assertJsonPath('data.clinic.email', 'contacto@clinica.com')
            ->assertJsonPath('data.services.0.id', $service->id)
            ->assertJsonPath('data.services.0.specialty.id', $service->specialty_id)
            ->assertJsonPath('data.dentists.0.id', $dentist->id)
            ->assertJsonPath('data.dentists.0.dentist_profile.user_id', $dentist->id);
    }

    public function test_only_active_services_from_pacient_clinic_are_returned(): void
    {
        [$clinic, $pacient, $service] = $this->makeClinicData();
        $otherClinic = Clinic::query()->create(['name' => 'Otra clínica']);
        $specialty = Specialty::query()->firstOrCreate(['name' => 'Endodoncia']);

        Service::query()->create([
            'clinic_id' => $clinic->id,
            'specialty_id' => $specialty->id,
            'name' => 'Inactivo',
            'duration_minutes' => 30,
            'price' => 250,
            'status' => false,
        ]);

        Service::query()->create([
            'clinic_id' => $otherClinic->id,
            'specialty_id' => $specialty->id,
            'name' => 'Ajeno',
            'duration_minutes' => 30,
            'price' => 300,
            'status' => true,
        ]);

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/clinic')
            ->assertOk()
            ->assertJsonCount(1, 'data.services')
            ->assertJsonPath('data.services.0.id', $service->id)
            ->assertJsonMissing(['name' => 'Inactivo'])
            ->assertJsonMissing(['name' => 'Ajeno']);
    }

    public function test_only_dentists_from_pacient_clinic_are_returned(): void
    {
        [$clinic, $pacient, , $dentist] = $this->makeClinicData();
        $otherClinic = Clinic::query()->create(['name' => 'Clínica externa']);

        $otherDentist = User::factory()->create([
            'clinic_id' => $otherClinic->id,
            'role' => 'dentist',
            'status' => true,
            'name' => 'Dentista ajeno',
        ]);

        DentistProfile::query()->create([
            'user_id' => $otherDentist->id,
            'clinic_id' => $otherClinic->id,
            'license_number' => 'EXT-100',
            'color' => '#000000',
        ]);

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/clinic')
            ->assertOk()
            ->assertJsonCount(1, 'data.dentists')
            ->assertJsonPath('data.dentists.0.id', $dentist->id)
            ->assertJsonMissing(['name' => 'Dentista ajeno']);
    }

    public function test_guest_receives_401(): void
    {
        $this->getJson('/api/pacient/clinic')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_other_roles_cannot_access_endpoint(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Roles']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $receptionist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'receptionist']);

        foreach ([$admin, $dentist, $receptionist] as $user) {
            Sanctum::actingAs($user);

            $this->getJson('/api/pacient/clinic')
                ->assertForbidden()
                ->assertJsonPath('message', 'Forbidden');
        }
    }

    private function makeClinicData(): array
    {
        $clinic = Clinic::query()->create([
            'name' => 'Clínica Centro',
            'email' => 'contacto@clinica.com',
            'phone' => '3312345678',
            'address' => 'Av. Juárez 123',
            'status' => true,
        ]);

        $pacient = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => 'pacient',
            'status' => true,
        ]);

        $dentist = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => 'dentist',
            'status' => true,
            'name' => 'Dra. Ana López',
            'email' => 'ana@clinica.com',
            'phone' => '3311111111',
        ]);

        DentistProfile::query()->create([
            'user_id' => $dentist->id,
            'clinic_id' => $clinic->id,
            'license_number' => 'CED-12345',
            'color' => '#00AAFF',
        ]);

        $specialty = Specialty::query()->create([
            'name' => 'Odontología general',
        ]);

        $service = Service::query()->create([
            'clinic_id' => $clinic->id,
            'specialty_id' => $specialty->id,
            'name' => 'Limpieza dental',
            'duration_minutes' => 45,
            'price' => 500,
            'status' => true,
        ]);

        return [$clinic, $pacient, $service, $dentist];
    }
}
