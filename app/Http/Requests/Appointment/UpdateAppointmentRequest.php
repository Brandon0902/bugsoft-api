<?php

namespace App\Http\Requests\Appointment;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'dentist_user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'start_at' => ['sometimes', 'required', 'date'],
            'end_at' => ['sometimes', 'required', 'date', 'after:start_at'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'internal_notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', Rule::in(Appointment::STATUSES)],
        ];
    }
}
