<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentNote;
use App\Models\Clinic;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PacientAppointmentExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pacient_can_export_full_history(): void
    {
        [$clinic, $dentist, $pacient] = $this->makeClinicDentistAndPacient();
        $service = $this->createServiceForClinic($clinic);
        $appointment = $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-02-10 10:00:00', '2026-02-10 10:30:00', 'completed', $service->id, 'Limpieza dental');
        $this->createNote($appointment->id, $dentist->id, 'Nota uno', '2026-02-10 11:00:00');

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/appointments/export')
            ->assertOk()
            ->assertJsonPath('message', 'Historial de citas exportado.')
            ->assertJsonPath('data.patient.id', $pacient->id)
            ->assertJsonPath('data.summary.total_appointments', 1)
            ->assertJsonPath('data.summary.total_notes', 1)
            ->assertJsonPath('data.appointments.0.id', $appointment->id)
            ->assertJsonPath('data.appointments.0.reason', 'Limpieza dental')
            ->assertJsonPath('data.appointments.0.service.id', $service->id)
            ->assertJsonPath('data.appointments.0.specialty.id', $service->specialty_id);
    }

    public function test_export_only_includes_authenticated_pacient_appointments(): void
    {
        [$clinic, $dentist, $pacient] = $this->makeClinicDentistAndPacient();
        $otherPacient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);
        $own = $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-02-10 10:00:00', '2026-02-10 10:30:00');
        $this->createAppointment($clinic->id, $otherPacient->id, $dentist->id, $dentist->id, '2026-02-11 10:00:00', '2026-02-11 10:30:00');

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/appointments/export')
            ->assertOk()
            ->assertJsonCount(1, 'data.appointments')
            ->assertJsonPath('data.appointments.0.id', $own->id);
    }

    public function test_each_exported_appointment_includes_notes_ordered_by_created_at(): void
    {
        [$clinic, $dentist, $pacient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-02-10 10:00:00', '2026-02-10 10:30:00');
        $this->createNote($appointment->id, $dentist->id, 'Segunda', '2026-02-10 12:00:00');
        $this->createNote($appointment->id, $dentist->id, 'Primera', '2026-02-10 11:00:00');

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/appointments/export')
            ->assertOk()
            ->assertJsonPath('data.appointments.0.notes.0.note', 'Primera')
            ->assertJsonPath('data.appointments.0.notes.1.note', 'Segunda');
    }

    public function test_appointments_are_ordered_by_start_at(): void
    {
        [$clinic, $dentist, $pacient] = $this->makeClinicDentistAndPacient();
        $first = $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-02-10 09:00:00', '2026-02-10 09:30:00');
        $second = $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-02-11 09:00:00', '2026-02-11 09:30:00');

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/appointments/export')
            ->assertOk()
            ->assertJsonPath('data.appointments.0.id', $first->id)
            ->assertJsonPath('data.appointments.1.id', $second->id);
    }

    public function test_from_filter_works(): void
    {
        [$clinic, $dentist, $pacient] = $this->makeClinicDentistAndPacient();
        $old = $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-01-10 09:00:00', '2026-01-10 09:30:00');
        $new = $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-03-10 09:00:00', '2026-03-10 09:30:00');

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/appointments/export?from=2026-02-01')
            ->assertOk()
            ->assertJsonCount(1, 'data.appointments')
            ->assertJsonPath('data.appointments.0.id', $new->id);

        $this->assertNotSame($old->id, $new->id);
    }

    public function test_to_filter_works(): void
    {
        [$clinic, $dentist, $pacient] = $this->makeClinicDentistAndPacient();
        $old = $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-01-10 09:00:00', '2026-01-10 09:30:00');
        $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-03-10 09:00:00', '2026-03-10 09:30:00');

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/appointments/export?to=2026-01-31')
            ->assertOk()
            ->assertJsonCount(1, 'data.appointments')
            ->assertJsonPath('data.appointments.0.id', $old->id);
    }

    public function test_status_filter_works(): void
    {
        [$clinic, $dentist, $pacient] = $this->makeClinicDentistAndPacient();
        $completed = $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-02-10 09:00:00', '2026-02-10 09:30:00', 'completed');
        $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-03-10 09:00:00', '2026-03-10 09:30:00', 'scheduled');

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/appointments/export?status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data.appointments')
            ->assertJsonPath('data.appointments.0.id', $completed->id)
            ->assertJsonPath('data.filters.status', 'completed');
    }

    public function test_combined_filters_work(): void
    {
        [$clinic, $dentist, $pacient] = $this->makeClinicDentistAndPacient();
        $match = $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-05-10 09:00:00', '2026-05-10 09:30:00', 'completed');
        $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-01-10 09:00:00', '2026-01-10 09:30:00', 'completed');
        $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-05-11 09:00:00', '2026-05-11 09:30:00', 'scheduled');

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/appointments/export?from=2026-05-01&to=2026-05-31&status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data.appointments')
            ->assertJsonPath('data.appointments.0.id', $match->id);
    }

    public function test_summary_counts_are_correct(): void
    {
        [$clinic, $dentist, $pacient] = $this->makeClinicDentistAndPacient();
        $first = $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id);
        $second = $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-02-11 09:00:00', '2026-02-11 09:30:00');
        $this->createNote($first->id, $dentist->id, 'Uno', '2026-02-10 11:00:00');
        $this->createNote($first->id, $dentist->id, 'Dos', '2026-02-10 12:00:00');
        $this->createNote($second->id, $dentist->id, 'Tres', '2026-02-11 11:00:00');

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/appointments/export')
            ->assertOk()
            ->assertJsonPath('data.summary.total_appointments', 2)
            ->assertJsonPath('data.summary.total_notes', 3);
    }

    public function test_other_roles_cannot_use_export_endpoint(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Export']);
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'admin']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $receptionist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'receptionist']);

        foreach ([$admin, $dentist, $receptionist] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/pacient/appointments/export')->assertForbidden();
        }
    }

    public function test_invalid_filters_return_422(): void
    {
        [, , $pacient] = $this->makeClinicDentistAndPacient();

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/appointments/export?status=invalido&from=2026-99-99')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'status']);
    }

    public function test_invalid_range_returns_422(): void
    {
        [, , $pacient] = $this->makeClinicDentistAndPacient();

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/appointments/export?from=2026-12-31&to=2026-01-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from']);
    }

    public function test_legacy_appointments_without_service_do_not_break_export(): void
    {
        [$clinic, $dentist, $pacient] = $this->makeClinicDentistAndPacient();
        $appointment = $this->createAppointment($clinic->id, $pacient->id, $dentist->id, $dentist->id, '2026-02-10 10:00:00', '2026-02-10 10:30:00', 'completed', null);
        $this->createNote($appointment->id, $dentist->id, 'Legacy', '2026-02-10 11:00:00');

        Sanctum::actingAs($pacient);

        $this->getJson('/api/pacient/appointments/export')
            ->assertOk()
            ->assertJsonPath('data.appointments.0.id', $appointment->id)
            ->assertJsonPath('data.appointments.0.service', null)
            ->assertJsonPath('data.appointments.0.specialty', null)
            ->assertJsonPath('data.appointments.0.notes.0.note', 'Legacy');
    }

    private function makeClinicDentistAndPacient(): array
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Export']);
        $dentist = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'dentist']);
        $pacient = User::factory()->create(['clinic_id' => $clinic->id, 'role' => 'pacient']);

        return [$clinic, $dentist, $pacient];
    }

    private function createAppointment(
        int $clinicId,
        int $patientId,
        int $dentistId,
        int $createdBy,
        string $startAt = '2026-02-10 10:00:00',
        string $endAt = '2026-02-10 10:30:00',
        string $status = 'scheduled',
        ?int $serviceId = null,
        ?string $reason = null
    ): Appointment {
        return Appointment::query()->create([
            'clinic_id' => $clinicId,
            'patient_user_id' => $patientId,
            'dentist_user_id' => $dentistId,
            'service_id' => $serviceId,
            'created_by' => $createdBy,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => $status,
            'reason' => $reason,
        ]);
    }

    private function createNote(int $appointmentId, int $authorUserId, string $note, string $createdAt): AppointmentNote
    {
        $appointmentNote = AppointmentNote::query()->create([
            'appointment_id' => $appointmentId,
            'author_user_id' => $authorUserId,
            'note' => $note,
        ]);

        $appointmentNote->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $appointmentNote->fresh();
    }

    private function createServiceForClinic(Clinic $clinic): Service
    {
        $specialty = Specialty::query()->firstOrCreate(['name' => 'Odontología general']);

        return Service::query()->create([
            'clinic_id' => $clinic->id,
            'specialty_id' => $specialty->id,
            'name' => 'Limpieza dental',
            'duration_minutes' => 30,
            'price' => 450,
            'status' => true,
        ]);
    }
}
