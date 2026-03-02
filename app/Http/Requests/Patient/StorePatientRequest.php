<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'boolean'],
            'profile.birth_date' => ['nullable', 'date'],
            'profile.gender' => ['nullable', 'string', 'max:20'],
            'profile.address' => ['nullable', 'string', 'max:255'],
            'profile.allergies' => ['nullable', 'string'],
            'profile.notes' => ['nullable', 'string'],
        ];
    }
}
