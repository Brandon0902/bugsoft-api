<?php

namespace App\Http\Requests\Auth;

use App\Models\Clinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegisterPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['required', 'integer', 'exists:clinics,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $clinicId = $this->input('clinic_id');

            if (! $clinicId) {
                return;
            }

            $clinic = Clinic::query()->find($clinicId);

            if (! $clinic) {
                return;
            }

            if (! $clinic->status) {
                $validator->errors()->add('clinic_id', 'La clínica seleccionada está inactiva.');
            }
        });
    }
}
