<?php

namespace Tests\Feature;

use App\Models\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicClinicControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_clinics_endpoint_lists_only_active_clinics_with_contact_fields(): void
    {
        $activeClinic = Clinic::query()->create([
            'name' => 'Clínica Centro',
            'email' => 'contacto@clinica.com',
            'phone' => '3312345678',
            'address' => 'Av. Juárez 123',
            'status' => true,
        ]);

        Clinic::query()->create([
            'name' => 'Clínica Inactiva',
            'email' => 'inactiva@clinica.com',
            'phone' => '3399999999',
            'address' => 'Calle Cerrada 456',
            'status' => false,
        ]);

        $this->getJson('/api/public/clinics')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $activeClinic->id)
            ->assertJsonPath('data.0.name', 'Clínica Centro')
            ->assertJsonPath('data.0.email', 'contacto@clinica.com')
            ->assertJsonPath('data.0.phone', '3312345678')
            ->assertJsonPath('data.0.address', 'Av. Juárez 123')
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['name' => 'Clínica Inactiva']);
    }
}
