<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentFrameRequest;
use App\Models\FaceEmbedding;
use App\Models\Student;
use App\Services\AiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FaceEnrollmentController extends Controller
{
    public function create(Student $student): View
    {
        return view('admin.students.enrollment', compact('student'));
    }

    public function reset(Student $student): RedirectResponse
    {
        DB::transaction(function () use ($student): void {
            $student->faceEmbeddings()->delete();
            $student->update(['is_face_enrolled' => false]);
        });

        return redirect()
            ->route('admin.students.enrollment.create', $student)
            ->with('status', 'Data wajah lama telah dihapus. Silakan ambil lima pose untuk pendaftaran ulang.');
    }

    public function store(StoreEnrollmentFrameRequest $request, Student $student, AiService $aiService): JsonResponse
    {
        try {
            $result = $aiService->enroll(
                $student,
                $request->string('pose')->toString(),
                $request->file('frame'),
            );
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json(['message' => 'Layanan pengenalan wajah belum siap. Pastikan FastAPI aktif.'], 503);
        }

        if (! ($result['accepted'] ?? false)) {
            return response()->json([
                'message' => $result['message'] ?? 'Wajah belum memenuhi kualitas pendaftaran.',
            ], 422);
        }

        FaceEmbedding::query()->updateOrCreate(
            ['student_id' => $student->id, 'pose' => $request->string('pose')->toString()],
            [
                'model' => $result['model'] ?? 'facenet-vggface2',
                'embedding' => $result['embedding'],
                'quality_score' => $result['quality_score'] ?? null,
            ],
        );

        $completedPoses = FaceEmbedding::query()->whereBelongsTo($student)->count();
        $isEnrolled = $completedPoses === 5;
        $student->update(['is_face_enrolled' => $isEnrolled]);

        return response()->json([
            'message' => $isEnrolled ? 'Pendaftaran wajah lengkap.' : 'Pose berhasil disimpan.',
            'completed_poses' => $completedPoses,
            'is_face_enrolled' => $isEnrolled,
        ]);
    }
}
