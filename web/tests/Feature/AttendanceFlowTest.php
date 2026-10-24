<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\FaceEmbedding;
use App\Models\LivenessAttempt;
use App\Models\Student;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_attendance_page_redirects_home_when_no_session_is_active(): void
    {
        $this->get(route('attendance.create'))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors(['attendance' => 'Belum ada sesi presensi yang aktif. Hubungi admin kelas.']);
    }

    public function test_attendance_page_provides_a_voice_guidance_toggle(): void
    {
        $this->activeSession();

        $this->get(route('attendance.create'))
            ->assertOk()
            ->assertSee('data-voice-toggle', false);
    }

    public function test_starting_liveness_creates_an_attempt_for_the_active_session(): void
    {
        $attendanceSession = $this->activeSession();
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8000/api/liveness/start' => Http::response([
                'session_id' => (string) Str::uuid(),
                'challenges' => ['blink', 'turn_left'],
                'instruction' => 'Kedipkan mata sekali',
                'challenge_mode' => 'snapshot',
                'challenge_timeout_seconds' => 0,
            ]),
        ]);

        $this->postJson(route('attendance.liveness.start'))
            ->assertOk()
            ->assertJsonPath('instruction', 'Kedipkan mata sekali')
            ->assertJsonPath('challenge_total', 2)
            ->assertJsonPath('challenge_mode', 'snapshot')
            ->assertJsonPath('challenge_timeout_seconds', 0);

        $this->assertDatabaseHas('liveness_attempts', [
            'attendance_session_id' => $attendanceSession->id,
            'passed' => false,
        ]);
    }

    public function test_liveness_attempt_remains_valid_while_the_attendance_session_is_active(): void
    {
        $attendanceSession = $this->activeSession();
        $attempt = LivenessAttempt::factory()->for($attendanceSession)->create(['created_at' => now()->subMinutes(10)]);
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8000/api/liveness/frame' => Http::response([
                'status' => 'continue',
                'instruction' => 'Arahkan wajah ke kanan',
                'challenge_number' => 2,
                'challenge_total' => 5,
                'challenge_mode' => 'snapshot',
                'challenge_timeout_seconds' => 0,
            ]),
        ]);

        $this->post(route('attendance.liveness.frame'), [
            'attempt_id' => $attempt->id,
            'frame' => UploadedFile::fake()->image('frame.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'continue')
            ->assertJsonPath('instruction', 'Arahkan wajah ke kanan')
            ->assertJsonPath('challenge_mode', 'snapshot')
            ->assertJsonPath('challenge_timeout_seconds', 0);

        $this->assertDatabaseHas('liveness_attempts', [
            'id' => $attempt->id,
            'passed' => false,
            'failure_reason' => null,
        ]);
    }

    public function test_completed_liveness_and_recognized_student_creates_one_attendance_record(): void
    {
        $classroom = Classroom::factory()->create();
        $student = Student::factory()->create();
        $classroom->students()->attach($student);
        $attendanceSession = AttendanceSession::factory()->for($classroom)->create([
            'status' => 'active',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
        ]);
        FaceEmbedding::factory()->for($student)->create();
        $attempt = LivenessAttempt::factory()->for($attendanceSession)->create([
            'passed' => true,
            'created_at' => now(),
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8000/api/recognition/identify' => Http::response([
                'recognized' => true,
                'student_id' => $student->id,
                'distance' => 0.36,
            ]),
        ]);

        $this->post(route('attendance.complete'), [
            'attempt_id' => $attempt->id,
            'frame' => UploadedFile::fake()->image('frame.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('student_name', $student->name);

        $this->assertDatabaseHas('attendances', [
            'attendance_session_id' => $attendanceSession->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);
    }

    private function activeSession(): AttendanceSession
    {
        return AttendanceSession::factory()->create([
            'status' => 'active',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
        ]);
    }
}
