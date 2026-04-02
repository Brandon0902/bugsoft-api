<?php

namespace App\Http\Requests\Pacient;

use Illuminate\Foundation\Http\FormRequest;

class StorePacientAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'dentist_user_id' => ['required', 'integer', 'exists:users,id'],
            'start_at' => ['required', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string'],
        ];
    }
}
