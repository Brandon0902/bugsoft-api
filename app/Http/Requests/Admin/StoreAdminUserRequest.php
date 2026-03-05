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
            'dentist_profile.specialty' => ['required_if:role,dentist', 'nullable', 'string', 'max:255'],
            'dentist_profile.license_number' => ['required_if:role,dentist', 'nullable', 'string', 'max:255'],
            'dentist_profile.color' => ['required_if:role,dentist', 'nullable', 'string', 'max:20'],
        ];
    }
}