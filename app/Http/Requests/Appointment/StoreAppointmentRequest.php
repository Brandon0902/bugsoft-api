<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_user_id' => ['required', 'integer', 'exists:users,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'dentist_user_id' => [
                Rule::requiredIf(fn (): bool => $this->user()?->role !== 'dentist'),
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'start_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
