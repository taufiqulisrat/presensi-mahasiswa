<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceReportRequest;
use App\Http\Requests\CompleteAttendanceRequest;
use App\Http\Requests\ProcessLivenessFrameRequest;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\FaceEmbedding;
use App\Models\LivenessAttempt;
use App\Models\Student;
use App\Services\AiService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function landing(): View
    {
        return view('home', ['attendanceSession' => $this->activeSession()]);
    }

    public function create(): View|RedirectResponse
    {
        $attendanceSession = $this->activeSession();

        if ($attendanceSession === null) {
            return redirect()->route('home')->withErrors(['attendance' => 'Belum ada sesi presensi yang aktif. Hubungi admin kelas.']);
        }

        return view('attendance.create', compact('attendanceSession'));
    }

    public function startLiveness(AiService $aiService): JsonResponse
    {
        $attendanceSession = $this->activeSession();

        if ($attendanceSession === null) {
            return response()->json(['message' => 'Sesi presensi sudah tidak aktif.'], 422);
        }

        try {
            $result = $aiService->startLiveness($attendanceSession);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json(['message' => 'Layanan liveness belum siap. Coba beberapa saat lagi.'], 503);
        }

        $attempt = LivenessAttempt::create([
            'attendance_session_id' => $attendanceSession->id,
            'ai_session_id' => $result['session_id'],
            'challenges' => $result['challenges'],
        ]);

        return response()->json([
            'attempt_id' => $attempt->id,
            'instruction' => $result['instruction'],
            'challenge_number' => 1,
            'challenge_total' => count($result['challenges']),
            'challenge_mode' => $result['challenge_mode'] ?? 'video',
            'challenge_timeout_seconds' => (int) ($result['challenge_timeout_seconds'] ?? 5),
        ]);
    }

    public function processLivenessFrame(ProcessLivenessFrameRequest $request, AiService $aiService): JsonResponse
    {
        $attempt = LivenessAttempt::query()
            ->with('attendanceSession')
            ->findOrFail($request->integer('attempt_id'));

        if ($attempt->passed || $attempt->failure_reason !== null) {
            return response()->json(['message' => 'Percobaan liveness ini sudah selesai. Mulai kembali.'], 422);
        }

        if (! $this->isAttemptUsable($attempt)) {
            return response()->json(['message' => 'Percobaan liveness sudah tidak berlaku. Mulai kembali.'], 422);
        }

        try {
            $result = $aiService->processLivenessFrame($attempt, $request->file('frame'));
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json(['message' => 'Frame kamera tidak dapat diproses.'], 503);
        }

        if (($result['status'] ?? null) === 'failed') {
            $attempt->update([
                'failure_reason' => $result['message'] ?? 'Respons challenge tidak sesuai.',
                'completed_at' => now(),
            ]);

            return response()->json(['message' => $attempt->failure_reason, 'status' => 'failed'], 422);
        }

        if (($result['status'] ?? null) === 'passed') {
            $attempt->update(['passed' => true, 'completed_at' => now()]);

            return response()->json(['status' => 'passed', 'message' => 'Liveness berhasil. Memeriksa identitas...']);
        }

        return response()->json([
            'status' => $result['status'] ?? 'continue',
            'instruction' => $result['instruction'],
            'challenge_number' => $result['challenge_number'],
            'challenge_total' => $result['challenge_total'],
            'challenge_mode' => $result['challenge_mode'] ?? 'video',
            'challenge_timeout_seconds' => (int) ($result['challenge_timeout_seconds'] ?? 5),
            'message' => $result['message'] ?? null,
        ]);
    }

    public function complete(CompleteAttendanceRequest $request, AiService $aiService): JsonResponse
    {
        $attempt = LivenessAttempt::query()
            ->with('attendanceSession.classroom')
            ->findOrFail($request->integer('attempt_id'));

        if (! $attempt->passed || ! $this->isAttemptUsable($attempt)) {
            return response()->json(['message' => 'Liveness belum valid atau sudah kedaluwarsa.'], 422);
        }

        $faceEmbeddings = FaceEmbedding::query()
            ->whereHas('student.classrooms', fn ($query) => $query->whereKey($attempt->attendanceSession->classroom_id))
            ->get();

        if ($faceEmbeddings->isEmpty()) {
            return response()->json(['message' => 'Belum ada wajah mahasiswa kelas ini yang terdaftar.'], 422);
        }

        try {
            $result = $aiService->identify($attempt->attendanceSession, $faceEmbeddings, $request->file('frame'));
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json(['message' => 'Identitas wajah tidak dapat diproses.'], 503);
        }

        if (! ($result['recognized'] ?? false)) {
            return response()->json(['message' => $result['message'] ?? 'Wajah tidak dikenali.'], 422);
        }

        $student = Student::query()
            ->whereKey($result['student_id'])
            ->whereHas('classrooms', fn ($query) => $query->whereKey($attempt->attendanceSession->classroom_id))
            ->first();

        if ($student === null) {
            return response()->json(['message' => 'Identitas tidak terdaftar pada kelas sesi ini.'], 422);
        }

        try {
            DB::transaction(function () use ($attempt, $student, $result): void {
                Attendance::create([
                    'attendance_session_id' => $attempt->attendance_session_id,
                    'student_id' => $student->id,
                    'similarity' => $result['distance'],
                    'attended_at' => now(),
                ]);

                $attempt->update(['student_id' => $student->id]);
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Presensi untuk mahasiswa ini sudah tercatat pada sesi aktif.'], 409);
        }

        return response()->json([
            'message' => 'Presensi berhasil dicatat.',
            'student_name' => $student->name,
        ]);
    }

    public function report(AttendanceReportRequest $request): View
    {
        $attendances = Attendance::query()
            ->with(['attendanceSession.course', 'attendanceSession.classroom', 'student'])
            ->when(
                $request->filled('attendance_session_id'),
                fn ($query) => $query->where('attendance_session_id', $request->integer('attendance_session_id')),
            )
            ->orderByDesc('attended_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.reports.index', [
            'attendances' => $attendances,
            'attendanceSessions' => AttendanceSession::query()
                ->with(['course', 'classroom'])
                ->orderByDesc('starts_at')
                ->get(),
        ]);
    }

    private function activeSession(): ?AttendanceSession
    {
        return AttendanceSession::query()
            ->with(['course', 'classroom'])
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }

    private function isAttemptUsable(LivenessAttempt $attempt): bool
    {
        return $attempt->attendanceSession->status === 'active'
            && $attempt->attendanceSession->ends_at->isFuture();
    }
}
