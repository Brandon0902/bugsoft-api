<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentNote;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_dentist_can_list_notes_for_own_appointment(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id);

        $olderNote = AppointmentNote::factory()->create([
            'appointment_id' => $appointment->id,
            'author_user_id' => $dentist->id,
            'note' => 'Primera nota',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $newerNote = AppointmentNote::factory()->create([
            'appointment_id' => $appointment->id,
            'author_user_id' => $dentist->id,
            'note' => 'Segunda nota',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($dentist);

        $this->getJson("/api/appointments/{$appointment->id}/notes")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $newerNote->id)
            ->assertJsonPath('data.1.id', $olderNote->id)
            ->assertJsonPath('data.0.author.id', $dentist->id);
    }

    public function test_dentist_can_create_note_for_own_appointment(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id);

        Sanctum::actingAs($dentist);

        $this->postJson("/api/appointments/{$appointment->id}/notes", [
            'note' => 'Paciente con evolucion favorable.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.appointment_id', $appointment->id)
            ->assertJsonPath('data.author_user_id', $dentist->id)
            ->assertJsonPath('data.note', 'Paciente con evolucion favorable.')
            ->assertJsonPath('data.author.id', $dentist->id);

        $this->assertDatabaseHas('appointment_notes', [
            'appointment_id' => $appointment->id,
            'author_user_id' => $dentist->id,
            'note' => 'Paciente con evolucion favorable.',
        ]);
    }

    public function test_dentist_can_show_note_for_own_appointment(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id);
        $note = AppointmentNote::factory()->create([
            'appointment_id' => $appointment->id,
            'author_user_id' => $dentist->id,
            'note' => 'Nota visible para el dentista.',
        ]);

        Sanctum::actingAs($dentist);

        $this->getJson("/api/appointments/{$appointment->id}/notes/{$note->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $note->id)
            ->assertJsonPath('data.author.id', $dentist->id);
    }

    public function test_dentist_cannot_access_notes_of_other_dentist_appointment(): void
    {
        [$clinic, $dentist] = $this->makeClinicDentistAndPacient();
        $otherDentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $otherPacient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $foreignAppointment = $this->createAppointment($clinic->id, $otherPacient->id, $otherDentist->id, $otherDentist->id);

        Sanctum::actingAs($dentist);

        $this->getJson("/api/appointments/{$foreignAppointment->id}/notes")->assertNotFound();
        $this->postJson("/api/appointments/{$foreignAppointment->id}/notes", ['note' => 'No'])
            ->assertNotFound();
    }

    public function test_dentist_store_ignores_author_user_id_from_payload(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $otherUser = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id);

        Sanctum::actingAs($dentist);

        $this->postJson("/api/appointments/{$appointment->id}/notes", [
            'author_user_id' => $otherUser->id,
            'appointment_id' => 999999,
            'note' => 'La autoria debe ignorarse.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.author_user_id', $dentist->id)
            ->assertJsonPath('data.appointment_id', $appointment->id);

        $this->assertDatabaseHas('appointment_notes', [
            'appointment_id' => $appointment->id,
            'author_user_id' => $dentist->id,
            'note' => 'La autoria debe ignorarse.',
        ]);
    }

    public function test_patient_can_list_notes_for_own_appointment(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id);
        $note = AppointmentNote::factory()->create([
            'appointment_id' => $appointment->id,
            'author_user_id' => $dentist->id,
            'note' => 'Indicaciones post consulta.',
        ]);

        Sanctum::actingAs($patient);

        $this->getJson("/api/pacient/appointments/{$appointment->id}/notes")
            ->assertOk()
            ->assertJsonPath('data.0.id', $note->id)
            ->assertJsonPath('data.0.author.id', $dentist->id);
    }

    public function test_patient_can_show_note_for_own_appointment(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id);
        $note = AppointmentNote::factory()->create([
            'appointment_id' => $appointment->id,
            'author_user_id' => $dentist->id,
            'note' => 'Diagnostico compartido.',
        ]);

        Sanctum::actingAs($patient);

        $this->getJson("/api/pacient/appointments/{$appointment->id}/notes/{$note->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $note->id)
            ->assertJsonPath('data.note', 'Diagnostico compartido.');
    }

    public function test_patient_cannot_view_notes_of_other_patient_appointment(): void
    {
        [$clinic, $dentist] = $this->makeClinicDentistAndPacient();
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $otherPatient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $appointment = $this->createAppointment($clinic->id, $otherPatient->id, $dentist->id, $dentist->id);

        Sanctum::actingAs($patient);

        $this->getJson("/api/pacient/appointments/{$appointment->id}/notes")->assertNotFound();
    }

    public function test_receptionist_cannot_access_note_routes(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Notes']);
        $receptionist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'receptionist']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $receptionist->id);

        Sanctum::actingAs($receptionist);

        $this->getJson("/api/appointments/{$appointment->id}/notes")->assertForbidden();
    }

    public function test_admin_cannot_access_note_routes(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Notes']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $appointment = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $admin->id);

        Sanctum::actingAs($admin);

        $this->getJson("/api/appointments/{$appointment->id}/notes")->assertForbidden();
    }

    public function test_note_must_belong_to_the_appointment_in_route(): void
    {
        [$clinic, $dentist, $patient] = $this->makeClinicDentistAndPacient();
        $otherPatient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $appointmentA = $this->createAppointment($clinic->id, $patient->id, $dentist->id, $dentist->id);
        $appointmentB = $this->createAppointment(
            $clinic->id,
            $otherPatient->id,
            $dentist->id,
            $dentist->id,
            '2026-04-10 11:00:00',
            '2026-04-10 11:30:00'
        );

        $note = AppointmentNote::factory()->create([
            'appointment_id' => $appointmentB->id,
            'author_user_id' => $dentist->id,
            'note' => 'Pertenece a la cita B.',
        ]);

        Sanctum::actingAs($dentist);

        $this->getJson("/api/appointments/{$appointmentA->id}/notes/{$note->id}")
            ->assertNotFound();
    }

    private function makeClinicDentistAndPacient(): array
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Notes']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);

        return [$clinic, $dentist, $patient];
    }

    private function createAppointment(
        int $clinicId,
        int $patientId,
        int $dentistId,
        int $createdBy,
        string $startAt = '2026-04-10 10:00:00',
        string $endAt = '2026-04-10 10:30:00'
    ): Appointment {
        return Appointment::query()->create([
            'clinic_id' => $clinicId,
            'patient_user_id' => $patientId,
            'dentist_user_id' => $dentistId,
            'created_by' => $createdBy,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => 'scheduled',
        ]);
    }
}
