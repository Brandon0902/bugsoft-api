<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;

class AvailableDentistsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'start_at' => ['required', 'date'],
            'date' => ['nullable', 'date'],
            'exclude_appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
        ];
    }
}
