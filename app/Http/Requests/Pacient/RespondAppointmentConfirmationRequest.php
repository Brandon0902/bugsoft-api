<?php

namespace App\Http\Requests\Pacient;

use Illuminate\Foundation\Http\FormRequest;

class RespondAppointmentConfirmationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'response' => ['required', 'string', 'in:yes,no'],
        ];
    }
}
