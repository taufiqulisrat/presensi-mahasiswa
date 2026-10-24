<?php

namespace Database\Factories;

use App\Models\FaceEmbedding;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaceEmbedding>
 */
class FaceEmbeddingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'pose' => 'front',
            'model' => 'facenet-vggface2',
            'embedding' => array_fill(0, 512, 0.01),
            'quality_score' => 0.9,
        ];
    }
}
