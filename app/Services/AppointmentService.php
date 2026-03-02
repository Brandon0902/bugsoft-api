<?php

namespace App\Services;

use App\Models\Appointment;

class AppointmentService
{
    public function hasDentistOverlap(int $clinicId, int $dentistId, string $startAt, string $endAt): bool
    {
        return Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('dentist_user_id', $dentistId)
            ->whereNotIn('status', ['canceled'])
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->exists();
    }
}
