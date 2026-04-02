<?php

namespace App\Http\Requests\Pacient;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ExportPacientAppointmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string', 'in:'.implode(',', Appointment::STATUSES)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('from');
            $to = $this->input('to');

            if (! $from || ! $to) {
                return;
            }

            if ($from > $to) {
                $validator->errors()->add('from', 'La fecha inicial no puede ser mayor que la fecha final.');
            }
        });
    }
}
