<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceSessionRequest;
use App\Http\Requests\UpdateAttendanceSessionRequest;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Course;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceSessionController extends Controller
{
    public function index(): View
    {
        return view('admin.attendance-sessions.index', [
            'attendanceSessions' => AttendanceSession::query()
                ->with(['course', 'classroom'])
                ->withCount('attendances')
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.attendance-sessions.create', $this->formData());
    }

    public function store(StoreAttendanceSessionRequest $request): RedirectResponse
    {
        AttendanceSession::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.attendance-sessions.index')->with('status', 'Sesi presensi berhasil dibuat.');
    }

    public function show(AttendanceSession $attendanceSession): RedirectResponse
    {
        return redirect()->route('admin.attendance-sessions.edit', $attendanceSession);
    }

    public function edit(AttendanceSession $attendanceSession): View
    {
        return view('admin.attendance-sessions.edit', [
            ...$this->formData(),
            'attendanceSession' => $attendanceSession,
        ]);
    }

    public function update(UpdateAttendanceSessionRequest $request, AttendanceSession $attendanceSession): RedirectResponse
    {
        if ($attendanceSession->status === 'active') {
            return back()->withErrors(['attendance_session' => 'Tutup sesi aktif sebelum mengubah jadwalnya.']);
        }

        $attendanceSession->update($request->validated());

        return redirect()->route('admin.attendance-sessions.index')->with('status', 'Sesi presensi berhasil diperbarui.');
    }

    public function destroy(AttendanceSession $attendanceSession): RedirectResponse
    {
        if ($attendanceSession->status === 'active') {
            return back()->withErrors(['attendance_session' => 'Sesi aktif tidak dapat dihapus. Tutup sesi terlebih dahulu.']);
        }

        $attendanceSession->delete();

        return redirect()->route('admin.attendance-sessions.index')->with('status', 'Sesi presensi berhasil dihapus.');
    }

    public function open(AttendanceSession $attendanceSession): RedirectResponse
    {
        if ($attendanceSession->ends_at->isPast()) {
            return back()->withErrors(['attendance_session' => 'Sesi yang sudah berakhir tidak dapat dibuka.']);
        }

        DB::transaction(function () use ($attendanceSession): void {
            AttendanceSession::query()
                ->where('status', 'active')
                ->whereKeyNot($attendanceSession->id)
                ->update(['status' => 'closed']);

            $attendanceSession->update([
                'status' => 'active',
                'starts_at' => $attendanceSession->starts_at->isFuture() ? now() : $attendanceSession->starts_at,
            ]);
        });

        return redirect()->route('admin.attendance-sessions.index')->with('status', 'Sesi presensi dibuka. Mahasiswa kini dapat melakukan absen.');
    }

    public function close(AttendanceSession $attendanceSession): RedirectResponse
    {
        $attendanceSession->update(['status' => 'closed']);

        return redirect()->route('admin.attendance-sessions.index')->with('status', 'Sesi presensi ditutup.');
    }

    /**
     * @return array{classrooms: Collection<int, Classroom>, courses: Collection<int, Course>}
     */
    private function formData(): array
    {
        return [
            'classrooms' => Classroom::query()->orderBy('code')->get(),
            'courses' => Course::query()->orderBy('code')->get(),
        ];
    }
}
