<?php

namespace Database\Factories;

use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSession>
 */
class AttendanceSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'course_id' => Course::factory(),
            'status' => 'draft',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
        ];
    }
}
