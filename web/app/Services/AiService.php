<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\FaceEmbedding;
use App\Models\LivenessAttempt;
use App\Models\Student;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiService
{
    /**
     * @return array<string, mixed>
     */
    public function startLiveness(AttendanceSession $attendanceSession): array
    {
        return $this->client()
            ->post('/api/liveness/start', [
                'attendance_session_id' => $attendanceSession->id,
            ])
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function processLivenessFrame(LivenessAttempt $attempt, UploadedFile $frame): array
    {
        return $this->postFrame('/api/liveness/frame', $frame, [
            'ai_session_id' => $attempt->ai_session_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function enroll(Student $student, string $pose, UploadedFile $frame): array
    {
        return $this->postFrame('/api/enrollment/frame', $frame, [
            'student_id' => $student->id,
            'pose' => $pose,
        ]);
    }

    /**
     * @param  Collection<int, FaceEmbedding>  $faceEmbeddings
     * @return array<string, mixed>
     */
    public function identify(
        AttendanceSession $attendanceSession,
        Collection $faceEmbeddings,
        UploadedFile $frame,
    ): array {
        $candidates = $faceEmbeddings
            ->map(fn ($faceEmbedding): array => [
                'student_id' => $faceEmbedding->student_id,
                'embedding' => $faceEmbedding->embedding,
            ])
            ->values()
            ->all();

        return $this->postFrame('/api/recognition/identify', $frame, [
            'attendance_session_id' => $attendanceSession->id,
            'candidates_json' => json_encode($candidates, JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param  array<string, string|int>  $payload
     * @return array<string, mixed>
     */
    private function postFrame(string $path, UploadedFile $frame, array $payload): array
    {
        $contents = file_get_contents($frame->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('Frame kamera tidak dapat dibaca.');
        }

        return $this->client()
            ->attach('frame', $contents, $frame->getClientOriginalName())
            ->post($path, $payload)
            ->throw()
            ->json();
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->baseUrl((string) config('services.face_ai.url'))
            ->connectTimeout(5)
            ->timeout((int) config('services.face_ai.timeout'));
    }
}
