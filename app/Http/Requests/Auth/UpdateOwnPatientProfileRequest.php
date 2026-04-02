<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnPatientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'current_password' => ['required_with:password', 'string', 'current_password'],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
            'profile' => ['sometimes', 'array:address,allergies,notes'],
            'profile.address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'profile.allergies' => ['sometimes', 'nullable', 'string'],
            'profile.notes' => ['sometimes', 'nullable', 'string'],
            'email' => ['prohibited'],
            'role' => ['prohibited'],
            'status' => ['prohibited'],
            'clinic_id' => ['prohibited'],
            'profile.user_id' => ['prohibited'],
            'profile.clinic_id' => ['prohibited'],
            'profile.birth_date' => ['prohibited'],
            'profile.gender' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'La contraseña actual no coincide.',
            'email.prohibited' => 'No se permite cambiar el email.',
            'role.prohibited' => 'No se permite cambiar el rol.',
            'status.prohibited' => 'No se permite cambiar el status.',
            'clinic_id.prohibited' => 'No se permite cambiar la clínica.',
            'profile.user_id.prohibited' => 'No se permite cambiar el perfil del paciente.',
            'profile.clinic_id.prohibited' => 'No se permite cambiar la clínica del perfil.',
            'profile.birth_date.prohibited' => 'No se permite cambiar la fecha de nacimiento.',
            'profile.gender.prohibited' => 'No se permite cambiar el género.',
            'profile.array' => 'El perfil contiene campos no permitidos.',
        ];
    }
}
