<?php

namespace Database\Factories;

use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('IF-26-?'),
            'name' => 'Informatika '.fake()->randomLetter(),
            'academic_year' => '2026/2027',
            'semester' => fake()->numberBetween(1, 8),
        ];
    }
}
