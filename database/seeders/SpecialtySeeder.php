<?php

namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;

class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
            'Odontología general',
            'Ortodoncia',
            'Endodoncia',
            'Periodoncia',
            'Odontopediatría',
            'Cirugía oral',
            'Prostodoncia',
            'Implantología',
        ];

        foreach ($specialties as $name) {
            Specialty::query()->updateOrCreate(
                ['name' => $name],
                ['status' => true],
            );
        }
    }
}
