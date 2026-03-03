<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\DentistProfile;
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

    public function test_super_admin_can_show_update_and_destroy_staff_from_same_clinic(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $clinic = Clinic::query()->create(['name' => 'Clinic Show']);
        $staff = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => 'receptionist',
            'status' => true,
        ]);

        Sanctum::actingAs($superAdmin);

        $this->getJson("/api/super/clinics/{$clinic->id}/users/{$staff->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $staff->id)
            ->assertJsonPath('data.dentist_profile', null);

        $this->patchJson("/api/super/clinics/{$clinic->id}/users/{$staff->id}", [
            'name' => 'Updated Name',
            'role' => 'dentist',
            'status' => false,
            'clinic_id' => 123,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.role', 'dentist')
            ->assertJsonPath('data.status', false)
            ->assertJsonPath('data.clinic_id', $clinic->id)
            ->assertJsonPath('data.dentist_profile.user_id', $staff->id);

        $this->assertDatabaseHas('dentist_profiles', [
            'user_id' => $staff->id,
            'clinic_id' => $clinic->id,
        ]);

        $this->deleteJson("/api/super/clinics/{$clinic->id}/users/{$staff->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
    }

    public function test_super_admin_blocked_when_staff_belongs_to_another_clinic(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $clinic = Clinic::query()->create(['name' => 'Clinic A']);
        $otherClinic = Clinic::query()->create(['name' => 'Clinic B']);
        $staff = User::factory()->create([
            'clinic_id' => $otherClinic->id,
            'role' => 'dentist',
        ]);

        Sanctum::actingAs($superAdmin);

        $this->getJson("/api/super/clinics/{$clinic->id}/users/{$staff->id}")->assertNotFound();
        $this->patchJson("/api/super/clinics/{$clinic->id}/users/{$staff->id}", ['name' => 'nope'])->assertNotFound();
        $this->deleteJson("/api/super/clinics/{$clinic->id}/users/{$staff->id}")->assertNotFound();
    }

    public function test_super_admin_cannot_operate_non_staff_roles(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $clinic = Clinic::query()->create(['name' => 'Clinic Role']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);

        Sanctum::actingAs($superAdmin);

        $this->getJson("/api/super/clinics/{$clinic->id}/users/{$admin->id}")->assertNotFound();
        $this->patchJson("/api/super/clinics/{$clinic->id}/users/{$admin->id}", ['name' => 'x'])->assertNotFound();
        $this->deleteJson("/api/super/clinics/{$clinic->id}/users/{$admin->id}")->assertNotFound();
    }

    public function test_super_admin_role_change_from_dentist_to_receptionist_removes_dentist_profile(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $clinic = Clinic::query()->create(['name' => 'Clinic Role Change']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        DentistProfile::query()->create(['user_id' => $dentist->id, 'clinic_id' => $clinic->id]);

        Sanctum::actingAs($superAdmin);

        $this->patchJson("/api/super/clinics/{$clinic->id}/users/{$dentist->id}", [
            'role' => 'receptionist',
        ])
            ->assertOk()
            ->assertJsonPath('data.role', 'receptionist')
            ->assertJsonPath('data.dentist_profile', null);

        $this->assertDatabaseMissing('dentist_profiles', [
            'user_id' => $dentist->id,
        ]);
    }
}
