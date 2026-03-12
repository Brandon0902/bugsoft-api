<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReceptionistCrudValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_receptionist_without_role_and_dentist_profile_is_ignored(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Admin Reception']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/receptionists', [
            'name' => 'Recep Admin',
            'email' => 'recep.admin@example.com',
            'password' => 'password123',
            'phone' => '5551231234',
            'status' => true,
            'dentist_profile' => [
                'specialty' => 'Orthodontics',
                'license_number' => 'L-123',
                'color' => '#ff9900',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.role', 'receptionist')
            ->assertJsonMissingPath('data.dentist_profile');

        $userId = (int) $response->json('data.id');

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'clinic_id' => $clinic->id,
            'role' => 'receptionist',
            'email' => 'recep.admin@example.com',
        ]);

        $this->assertDatabaseMissing('dentist_profiles', [
            'user_id' => $userId,
        ]);
    }

    public function test_admin_can_update_receptionist_without_role_and_password_only_changes_when_sent(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Admin Update']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);
        $receptionist = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => 'receptionist',
            'password' => Hash::make('old-password-123'),
            'status' => true,
        ]);

        Sanctum::actingAs($admin);

        $oldPasswordHash = $receptionist->password;

        $this->patchJson("/api/admin/receptionists/{$receptionist->id}", [
            'name' => 'No Password Change',
            'dentist_profile' => ['specialty' => 'Ignored'],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'No Password Change')
            ->assertJsonPath('data.role', 'receptionist');

        $receptionist->refresh();
        $this->assertSame($oldPasswordHash, $receptionist->password);

        $this->patchJson("/api/admin/receptionists/{$receptionist->id}", [
            'password' => 'new-password-123',
        ])
            ->assertOk()
            ->assertJsonPath('data.role', 'receptionist');

        $receptionist->refresh();
        $this->assertNotSame($oldPasswordHash, $receptionist->password);
        $this->assertTrue(Hash::check('new-password-123', $receptionist->password));
    }

    public function test_super_admin_can_create_receptionist_without_role_and_dentist_profile_is_ignored(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $clinic = Clinic::query()->create(['name' => 'Clinic Super Reception']);

        Sanctum::actingAs($superAdmin);

        $response = $this->postJson("/api/super/clinics/{$clinic->id}/receptionists", [
            'name' => 'Recep Super',
            'email' => 'recep.super@example.com',
            'password' => 'password123',
            'status' => false,
            'dentist_profile' => [
                'specialty' => 'Ignored',
                'license_number' => 'IGN-001',
                'color' => '#000000',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.role', 'receptionist')
            ->assertJsonMissingPath('data.dentist_profile');

        $userId = (int) $response->json('data.id');

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'clinic_id' => $clinic->id,
            'role' => 'receptionist',
        ]);

        $this->assertDatabaseMissing('dentist_profiles', [
            'user_id' => $userId,
        ]);
    }

    public function test_super_admin_can_update_receptionist_without_role_and_password_only_changes_when_sent(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $clinic = Clinic::query()->create(['name' => 'Clinic Super Update']);
        $receptionist = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => 'receptionist',
            'password' => Hash::make('old-password-123'),
            'status' => true,
        ]);

        Sanctum::actingAs($superAdmin);

        $oldPasswordHash = $receptionist->password;

        $this->patchJson("/api/super/clinics/{$clinic->id}/receptionists/{$receptionist->id}", [
            'email' => 'receptionist.updated@example.com',
            'dentist_profile' => ['license_number' => 'IGN'],
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'receptionist.updated@example.com')
            ->assertJsonPath('data.role', 'receptionist');

        $receptionist->refresh();
        $this->assertSame($oldPasswordHash, $receptionist->password);

        $this->patchJson("/api/super/clinics/{$clinic->id}/receptionists/{$receptionist->id}", [
            'password' => 'new-password-456',
        ])
            ->assertOk()
            ->assertJsonPath('data.role', 'receptionist');

        $receptionist->refresh();
        $this->assertNotSame($oldPasswordHash, $receptionist->password);
        $this->assertTrue(Hash::check('new-password-456', $receptionist->password));
    }
}
