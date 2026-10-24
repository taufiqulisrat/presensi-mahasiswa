<?php

namespace Database\Factories;

use App\Models\AttendanceSession;
use App\Models\LivenessAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LivenessAttempt>
 */
class LivenessAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_session_id' => AttendanceSession::factory(),
            'ai_session_id' => (string) Str::uuid(),
            'challenges' => ['turn_left', 'blink'],
            'passed' => false,
        ];
    }
}
