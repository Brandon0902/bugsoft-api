<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminUserRequest extends FormRequest
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
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['receptionist', 'dentist'])],
            'status' => ['nullable', 'boolean'],

            // ---- Dentist profile (solo cuando role = dentist) ----
            'dentist_profile' => ['nullable', 'array'],
            'dentist_profile.specialty' => ['nullable', 'string', 'max:255'],
            'dentist_profile.license_number' => ['nullable', 'string', 'max:255'],
            'dentist_profile.color' => ['nullable', 'string', 'max:20'],
            'specialty_ids' => ['nullable', 'array'],
            'specialty_ids.*' => ['integer', 'distinct', 'exists:specialties,id'],
        ];
    }
}
