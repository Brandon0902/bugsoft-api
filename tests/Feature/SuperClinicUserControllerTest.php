<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperClinicUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_dentist_in_selected_clinic(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $clinic = Clinic::query()->create(['name' => 'Clinic One']);

        Sanctum::actingAs($superAdmin);

        $response = $this->postJson("/api/super/clinics/{$clinic->id}/users", [
            'name' => 'Dra. Lopez',
            'email' => 'dentist@example.com',
            'password' => 'password123',
            'role' => 'dentist',
            'clinic_id' => 999,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'dentist')
            ->assertJsonPath('data.clinic_id', $clinic->id)
            ->assertJsonPath('data.dentist_profile.clinic_id', $clinic->id);

        $this->assertDatabaseHas('users', [
            'email' => 'dentist@example.com',
            'clinic_id' => $clinic->id,
            'role' => 'dentist',
        ]);
    }

    public function test_super_admin_can_create_receptionist_in_selected_clinic(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $clinic = Clinic::query()->create(['name' => 'Clinic Two']);

        Sanctum::actingAs($superAdmin);

        $response = $this->postJson("/api/super/clinics/{$clinic->id}/users", [
            'name' => 'Reception User',
            'email' => 'reception@example.com',
            'password' => 'password123',
            'role' => 'receptionist',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'receptionist')
            ->assertJsonPath('data.clinic_id', $clinic->id);

        $this->assertDatabaseHas('users', [
            'email' => 'reception@example.com',
            'clinic_id' => $clinic->id,
            'role' => 'receptionist',
        ]);
        $this->assertDatabaseMissing('dentist_profiles', [
            'user_id' => $response->json('data.id'),
        ]);
    }

    public function test_dentist_profile_is_created_when_role_is_dentist(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $clinic = Clinic::query()->create(['name' => 'Clinic Three']);

        Sanctum::actingAs($superAdmin);

        $response = $this->postJson("/api/super/clinics/{$clinic->id}/users", [
            'name' => 'Dra. Profile',
            'email' => 'profile@example.com',
            'password' => 'password123',
            'role' => 'dentist',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('dentist_profiles', [
            'user_id' => $response->json('data.id'),
            'clinic_id' => $clinic->id,
        ]);
    }

    public function test_super_endpoint_rejects_invalid_role(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $clinic = Clinic::query()->create(['name' => 'Clinic Four']);

        Sanctum::actingAs($superAdmin);

        $response = $this->postJson("/api/super/clinics/{$clinic->id}/users", [
            'name' => 'Wrong Role',
            'email' => 'wrongrole@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation error');
    }

    public function test_admin_cannot_access_super_clinic_users_endpoint(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Five']);
        $admin = User::factory()->create(['role' => 'admin', 'clinic_id' => $clinic->id]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/super/clinics/{$clinic->id}/users", [
            'name' => 'Should Fail',
            'email' => 'fail@example.com',
            'password' => 'password123',
            'role' => 'dentist',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden');
    }
}
