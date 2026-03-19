<?php

namespace App\Http\Requests\Specialty;

use App\Models\Specialty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSpecialtyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $specialty = $this->route('specialty');
        $specialtyId = $specialty instanceof Specialty ? $specialty->id : $specialty;

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('specialties', 'name')->ignore($specialtyId)],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
