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
            'dentist_user_id' => [
                Rule::requiredIf(fn (): bool => $this->user()?->role !== 'dentist'),
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'reason' => ['nullable', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string'],
        ];
    }
}
