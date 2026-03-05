<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\DentistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;


    public function test_admin_index_lists_only_staff_from_own_clinic_with_dentist_profile_loaded(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Index']);
        $otherClinic = Clinic::query()->create(['name' => 'Clinic Other']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);

        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        DentistProfile::query()->create(['user_id' => $dentist->id, 'clinic_id' => $clinic->id]);

        $receptionist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'receptionist']);
        User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'client']);
        User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);
        User::factory()->create(['clinic_id' => $otherClinic->id, 'role' => 'dentist']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $dentist->id])
            ->assertJsonFragment(['id' => $receptionist->id]);

        $roles = collect($response->json('data'))->pluck('role')->sort()->values()->all();
        $this->assertSame(['dentist', 'receptionist'], $roles);

        $dentistItem = collect($response->json('data'))->firstWhere('id', $dentist->id);
        $this->assertNotNull($dentistItem['dentist_profile']);
    }

    public function test_admin_can_show_update_and_destroy_staff_in_own_clinic(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Admin']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);
        $staff = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => 'receptionist',
            'status' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/users/{$staff->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $staff->id);

        $this->patchJson("/api/admin/users/{$staff->id}", [
            'email' => 'updated.staff@example.com',
            'password' => 'newpassword123',
            'role' => 'dentist',
            'clinic_id' => 999,
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'updated.staff@example.com')
            ->assertJsonPath('data.role', 'dentist')
            ->assertJsonPath('data.clinic_id', $clinic->id)
            ->assertJsonPath('data.dentist_profile.user_id', $staff->id);

        $this->assertDatabaseHas('dentist_profiles', [
            'user_id' => $staff->id,
            'clinic_id' => $clinic->id,
        ]);

        $this->deleteJson("/api/admin/users/{$staff->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
    }

    public function test_admin_cannot_operate_staff_from_another_clinic(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic A']);
        $otherClinic = Clinic::query()->create(['name' => 'Clinic B']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);
        $staff = User::factory()->create(['clinic_id' => $otherClinic->id, 'role' => 'dentist']);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/users/{$staff->id}")->assertNotFound();
        $this->patchJson("/api/admin/users/{$staff->id}", ['name' => 'No'])->assertNotFound();
        $this->deleteJson("/api/admin/users/{$staff->id}")->assertNotFound();
    }

    public function test_admin_cannot_operate_admin_or_super_admin_using_staff_endpoints(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Roles']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);
        $otherAdmin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);
        $superAdmin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'super_admin']);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/users/{$otherAdmin->id}")->assertNotFound();
        $this->patchJson("/api/admin/users/{$otherAdmin->id}", ['name' => 'No'])->assertNotFound();
        $this->deleteJson("/api/admin/users/{$otherAdmin->id}")->assertNotFound();

        $this->getJson("/api/admin/users/{$superAdmin->id}")->assertNotFound();
        $this->patchJson("/api/admin/users/{$superAdmin->id}", ['name' => 'No'])->assertNotFound();
        $this->deleteJson("/api/admin/users/{$superAdmin->id}")->assertNotFound();
    }

    public function test_admin_role_change_from_dentist_to_receptionist_removes_dentist_profile(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Role']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        DentistProfile::query()->create(['user_id' => $dentist->id, 'clinic_id' => $clinic->id]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$dentist->id}", [
            'role' => 'receptionist',
        ])
            ->assertOk()
            ->assertJsonPath('data.role', 'receptionist')
            ->assertJsonPath('data.dentist_profile', null);

        $this->assertDatabaseMissing('dentist_profiles', ['user_id' => $dentist->id]);
    }
}
