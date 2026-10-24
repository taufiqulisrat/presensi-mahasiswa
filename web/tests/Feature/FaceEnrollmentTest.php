<?php

namespace Tests\Feature;

use App\Models\FaceEmbedding;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FaceEnrollmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_when_resetting_face_enrollment(): void
    {
        $student = Student::factory()->create();

        $this->post(route('admin.students.enrollment.reset', $student))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_clear_old_face_embeddings_before_reenrollment(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $student = Student::factory()->create(['is_face_enrolled' => true]);

        foreach (['front', 'left', 'right', 'up', 'down'] as $pose) {
            FaceEmbedding::factory()->for($student)->create(['pose' => $pose]);
        }

        $this->actingAs($admin)
            ->post(route('admin.students.enrollment.reset', $student))
            ->assertRedirect(route('admin.students.enrollment.create', $student))
            ->assertSessionHas('status', 'Data wajah lama telah dihapus. Silakan ambil lima pose untuk pendaftaran ulang.');

        $this->assertDatabaseMissing('face_embeddings', ['student_id' => $student->id]);
        $this->assertFalse($student->fresh()->is_face_enrolled);
    }

    public function test_regular_user_cannot_clear_old_face_embeddings(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $student = Student::factory()->create(['is_face_enrolled' => true]);
        FaceEmbedding::factory()->for($student)->create();

        $this->actingAs($user)
            ->post(route('admin.students.enrollment.reset', $student))
            ->assertForbidden();

        $this->assertDatabaseHas('face_embeddings', ['student_id' => $student->id]);
        $this->assertTrue($student->fresh()->is_face_enrolled);
    }
}
