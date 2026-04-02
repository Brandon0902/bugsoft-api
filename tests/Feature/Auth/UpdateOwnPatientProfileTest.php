<?php

namespace Tests\Feature\Auth;

use App\Models\Clinic;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateOwnPatientProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_pacient_can_view_me_with_patient_profile(): void
    {
        [$clinic, $pacient] = $this->makePacientWithProfile();

        Sanctum::actingAs($pacient);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Perfil obtenido.')
            ->assertJsonPath('data.id', $pacient->id)
            ->assertJsonPath('data.clinic_id', $clinic->id)
            ->assertJsonPath('data.role', 'pacient')
            ->assertJsonPath('data.patient_profile.user_id', $pacient->id)
            ->assertJsonPath('data.patient_profile.address', 'Av. Juárez 100');
    }

    public function test_pacient_can_update_own_name_and_phone(): void
    {
        [$clinic, $pacient] = $this->makePacientWithProfile();

        Sanctum::actingAs($pacient);

        $this->patchJson('/api/auth/me', [
            'name' => 'Juan Pérez Actualizado',
            'phone' => '3312345678',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Perfil actualizado.')
            ->assertJsonPath('data.id', $pacient->id)
            ->assertJsonPath('data.clinic_id', $clinic->id)
            ->assertJsonPath('data.name', 'Juan Pérez Actualizado')
            ->assertJsonPath('data.phone', '3312345678')
            ->assertJsonPath('data.patient_profile.user_id', $pacient->id);

        $this->assertDatabaseHas('users', [
            'id' => $pacient->id,
            'name' => 'Juan Pérez Actualizado',
            'phone' => '3312345678',
            'clinic_id' => $clinic->id,
            'role' => 'pacient',
        ]);
    }

    public function test_pacient_can_update_own_profile_fields(): void
    {
        [$clinic, $pacient] = $this->makePacientWithProfile();

        Sanctum::actingAs($pacient);

        $this->patchJson('/api/auth/me', [
            'profile' => [
                'address' => 'Av. Juárez 123',
                'allergies' => 'Penicilina',
                'notes' => 'Paciente con observaciones iniciales',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.patient_profile.user_id', $pacient->id)
            ->assertJsonPath('data.patient_profile.clinic_id', $clinic->id)
            ->assertJsonPath('data.patient_profile.address', 'Av. Juárez 123')
            ->assertJsonPath('data.patient_profile.allergies', 'Penicilina')
            ->assertJsonPath('data.patient_profile.notes', 'Paciente con observaciones iniciales');

        $this->assertDatabaseHas('patient_profiles', [
            'user_id' => $pacient->id,
            'clinic_id' => $clinic->id,
            'address' => 'Av. Juárez 123',
            'allergies' => 'Penicilina',
            'notes' => 'Paciente con observaciones iniciales',
        ]);
    }

    public function test_pacient_can_change_password_when_current_password_is_correct(): void
    {
        [, $pacient] = $this->makePacientWithProfile();

        Sanctum::actingAs($pacient);

        $this->patchJson('/api/auth/me', [
            'current_password' => 'Secret123',
            'password' => 'NuevaSecret123',
            'password_confirmation' => 'NuevaSecret123',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Perfil actualizado.');

        $this->assertTrue(Hash::check('NuevaSecret123', $pacient->fresh()->password));
    }

    public function test_password_change_fails_with_422_when_current_password_is_incorrect(): void
    {
        [, $pacient] = $this->makePacientWithProfile();

        Sanctum::actingAs($pacient);

        $this->patchJson('/api/auth/me', [
            'current_password' => 'Incorrecta123',
            'password' => 'NuevaSecret123',
            'password_confirmation' => 'NuevaSecret123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation error')
            ->assertJsonPath('errors.current_password.0', 'La contraseña actual no coincide.');
    }

    public function test_pacient_cannot_change_email(): void
    {
        [, $pacient] = $this->makePacientWithProfile();

        Sanctum::actingAs($pacient);

        $this->patchJson('/api/auth/me', [
            'email' => 'otro@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'No se permite cambiar el email.');
    }

    public function test_pacient_cannot_change_role(): void
    {
        [, $pacient] = $this->makePacientWithProfile();

        Sanctum::actingAs($pacient);

        $this->patchJson('/api/auth/me', [
            'role' => 'admin',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.role.0', 'No se permite cambiar el rol.');
    }

    public function test_pacient_cannot_change_status(): void
    {
        [, $pacient] = $this->makePacientWithProfile();

        Sanctum::actingAs($pacient);

        $this->patchJson('/api/auth/me', [
            'status' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'No se permite cambiar el status.');
    }

    public function test_pacient_cannot_change_clinic_id(): void
    {
        [$clinic, $pacient] = $this->makePacientWithProfile();

        Sanctum::actingAs($pacient);

        $this->patchJson('/api/auth/me', [
            'clinic_id' => $clinic->id + 1,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.clinic_id.0', 'No se permite cambiar la clínica.');
    }

    public function test_guest_cannot_update_own_patient_profile(): void
    {
        $this->patchJson('/api/auth/me', [
            'name' => 'Invitado',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_authenticated_non_pacient_user_cannot_use_patch_me(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clínica Staff']);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => 'admin',
            'status' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/auth/me', [
            'name' => 'No permitido',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden')
            ->assertJsonPath('errors.role.0', 'No autorizado para este recurso.');
    }

    public function test_patch_creates_patient_profile_if_missing(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clínica sin perfil']);
        $pacient = User::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Paciente sin perfil',
            'email' => 'sinperfil@example.com',
            'password' => 'Secret123',
            'phone' => '3311111111',
            'role' => 'pacient',
            'status' => true,
        ]);

        Sanctum::actingAs($pacient);

        $this->patchJson('/api/auth/me', [
            'profile' => [
                'address' => 'Nueva dirección',
                'allergies' => 'Ninguna',
                'notes' => 'Creado en update',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.patient_profile.user_id', $pacient->id)
            ->assertJsonPath('data.patient_profile.clinic_id', $clinic->id);

        $this->assertDatabaseHas('patient_profiles', [
            'user_id' => $pacient->id,
            'clinic_id' => $clinic->id,
            'address' => 'Nueva dirección',
            'allergies' => 'Ninguna',
            'notes' => 'Creado en update',
        ]);
    }

    private function makePacientWithProfile(): array
    {
        $clinic = Clinic::query()->create(['name' => 'Clínica paciente']);
        $pacient = User::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'password' => 'Secret123',
            'phone' => '3310000000',
            'role' => 'pacient',
            'status' => true,
        ]);

        PatientProfile::query()->create([
            'user_id' => $pacient->id,
            'clinic_id' => $clinic->id,
            'address' => 'Av. Juárez 100',
            'allergies' => 'Ninguna',
            'notes' => 'Perfil inicial',
        ]);

        return [$clinic, $pacient];
    }
}
