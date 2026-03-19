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
            'dentist_user_id' => [
                Rule::prohibitedIf(fn (): bool => $this->user()?->role === 'dentist'),
                'sometimes',
                'required',
                'integer',
                'exists:users,id',
            ],
            'start_at' => ['sometimes', 'required', 'date'],
            'end_at' => ['sometimes', 'required', 'date'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'internal_notes' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', Rule::in(Appointment::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'dentist_user_id.prohibited' => 'El campo dentist user id está prohibido.',
        ];
    }
}
