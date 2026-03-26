<?php

namespace App\Http\Requests\AppointmentNote;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:5000'],
        ];
    }
}
