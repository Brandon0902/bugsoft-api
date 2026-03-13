<?php

namespace App\Services;

use App\Models\Appointment;

class AppointmentService
{
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
}
