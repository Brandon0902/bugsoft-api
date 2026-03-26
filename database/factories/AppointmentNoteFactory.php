<?php

namespace Database\Factories;

use App\Models\AppointmentNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentNote>
 */
class AppointmentNoteFactory extends Factory
{
    protected $model = AppointmentNote::class;

    public function definition(): array
    {
        return [
            'appointment_id' => 1,
            'author_user_id' => 1,
            'note' => fake()->paragraph(),
        ];
    }
}
