<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $patientId = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($patientId)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'status' => ['sometimes', 'boolean'],
            'profile.birth_date' => ['sometimes', 'nullable', 'date'],
            'profile.gender' => ['sometimes', 'nullable', 'string', 'max:20'],
            'profile.address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'profile.allergies' => ['sometimes', 'nullable', 'string'],
            'profile.notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
