<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : $user;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'role' => ['sometimes', Rule::in(['receptionist', 'dentist'])],
            'status' => ['sometimes', 'boolean'],
            'dentist_profile' => ['nullable', 'array'],
            'dentist_profile.specialty' => ['nullable', 'string', 'max:255'],
            'dentist_profile.license_number' => ['nullable', 'string', 'max:255'],
            'dentist_profile.color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
