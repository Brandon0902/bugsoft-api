<?php

namespace Tests\Feature\Auth;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class RegisterPatientTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_patient_in_active_clinic_and_returns_token_payload(): void
    {
        $clinic = Clinic::query()->create([
            'name' => 'Clínica Centro',
            'status' => true,
        ]);

        $response = $this->postJson('/api/auth/register-patient', [
            'clinic_id' => $clinic->id,
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'phone' => '3312345678',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Paciente registrado.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.clinic_id', $clinic->id)
            ->assertJsonPath('data.user.name', 'Juan Pérez')
            ->assertJsonPath('data.user.email', 'juan@example.com')
            ->assertJsonPath('data.user.phone', '3312345678')
            ->assertJsonPath('data.user.role', 'pacient')
            ->assertJsonPath('data.user.status', true)
            ->assertJsonPath('data.user.patient_profile.clinic_id', $clinic->id)
            ->assertJsonPath('data.user.patient_profile.birth_date', null)
            ->assertJsonPath('data.user.patient_profile.gender', null)
            ->assertJsonPath('data.user.patient_profile.address', null)
            ->assertJsonPath('data.user.patient_profile.allergies', null)
            ->assertJsonPath('data.user.patient_profile.notes', null);

        $this->assertNotEmpty($response->json('data.token'));

        $userId = (int) $response->json('data.user.id');
        $plainTextToken = $response->json('data.token');
        $tokenId = (int) explode('|', $plainTextToken)[0];

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => 'juan@example.com',
            'clinic_id' => $clinic->id,
            'role' => 'pacient',
            'status' => true,
        ]);
        $this->assertDatabaseHas('patient_profiles', [
            'user_id' => $userId,
            'clinic_id' => $clinic->id,
            'birth_date' => null,
            'gender' => null,
            'address' => null,
            'allergies' => null,
            'notes' => null,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
            'tokenable_id' => $userId,
            'tokenable_type' => User::class,
        ]);
        $this->assertNotNull(PersonalAccessToken::query()->find($tokenId));
    }

    public function test_registration_fails_when_clinic_id_does_not_exist(): void
    {
        $this->postJson('/api/auth/register-patient', [
            'clinic_id' => 999,
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation error')
            ->assertJsonValidationErrors(['clinic_id']);
    }

    public function test_registration_fails_when_clinic_is_inactive(): void
    {
        $clinic = Clinic::query()->create([
            'name' => 'Clínica Inactiva',
            'status' => false,
        ]);

        $this->postJson('/api/auth/register-patient', [
            'clinic_id' => $clinic->id,
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation error')
            ->assertJsonPath('errors.clinic_id.0', 'La clínica seleccionada está inactiva.');
    }

    public function test_registration_fails_when_email_already_exists(): void
    {
        $clinic = Clinic::query()->create([
            'name' => 'Clínica Centro',
            'status' => true,
        ]);

        User::factory()->create([
            'email' => 'juan@example.com',
            'clinic_id' => $clinic->id,
        ]);

        $this->postJson('/api/auth/register-patient', [
            'clinic_id' => $clinic->id,
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation error')
            ->assertJsonValidationErrors(['email']);
    }

    public function test_client_sent_role_and_status_are_ignored_and_server_forces_patient_defaults(): void
    {
        $clinic = Clinic::query()->create([
            'name' => 'Clínica Centro',
            'status' => true,
        ]);

        $response = $this->postJson('/api/auth/register-patient', [
            'clinic_id' => $clinic->id,
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'role' => 'admin',
            'status' => false,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.role', 'pacient')
            ->assertJsonPath('data.user.status', true);

        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.com',
            'role' => 'pacient',
            'status' => true,
        ]);
    }
}
