<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class StoreClinicWithAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic.name' => ['required', 'string', 'max:160'],
            'clinic.phone' => ['nullable', 'string', 'max:30'],
            'clinic.email' => ['nullable', 'email', 'max:120'],
            'clinic.address' => ['nullable', 'string', 'max:255'],
            'clinic.status' => ['nullable', 'boolean'],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin.password' => ['required', 'string', 'min:8'],
            'admin.phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
