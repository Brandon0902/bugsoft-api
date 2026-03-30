<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class AppointmentService
{
    public function findCompatibleDentists(int $clinicId, int $specialtyId): Collection
    {
        return User::query()
            ->where('clinic_id', $clinicId)
            ->where('role', 'dentist')
            ->where('status', true)
            ->whereHas('dentistProfile.specialties', fn ($query) => $query->where('specialties.id', $specialtyId))
            ->with([
                'dentistProfile:id,user_id,clinic_id,license_number,color',
                'dentistProfile.specialties:id,name',
            ])
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();
    }

    public function hasDentistOverlap(
        int $clinicId,
        int $dentistId,
        string $startAt,
        string $endAt,
        ?int $ignoreAppointmentId = null
    ): bool
    {
        $query = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('dentist_user_id', $dentistId)
            ->whereNotIn('status', ['canceled'])
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt);

        if ($ignoreAppointmentId !== null) {
            $query->where('id', '!=', $ignoreAppointmentId);
        }

        return $query->exists();
    }

    public function hasDentistOverlapExceptAppointment(
        int $clinicId,
        int $dentistId,
        string $startAt,
        string $endAt,
        int $ignoreAppointmentId
    ): bool {
        return $this->hasDentistOverlap($clinicId, $dentistId, $startAt, $endAt, $ignoreAppointmentId);
    }

    public function calculateEndAtFromServiceDuration(string $startAt, int $durationMinutes): Carbon
    {
        return Carbon::parse($startAt)->addMinutes($durationMinutes);
    }

    public function findAvailableDentists(
        int $clinicId,
        int $specialtyId,
        string $requestedStartAt,
        string $requestedEndAt,
        ?int $excludeAppointmentId = null,
    ): Collection {
        $busyDentistIds = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->whereNotIn('status', ['canceled'])
            ->where('start_at', '<', $requestedEndAt)
            ->where('end_at', '>', $requestedStartAt)
            ->when(
                $excludeAppointmentId !== null,
                fn ($query) => $query->where('id', '!=', $excludeAppointmentId)
            )
            ->pluck('dentist_user_id');

        return $this->findCompatibleDentists($clinicId, $specialtyId)
            ->whereNotIn('id', $busyDentistIds)
            ->values();
    }
}
