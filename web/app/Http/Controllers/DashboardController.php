<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Student;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'studentCount' => Student::count(),
            'enrolledStudentCount' => Student::where('is_face_enrolled', true)->count(),
            'activeSession' => AttendanceSession::query()
                ->with(['course', 'classroom'])
                ->where('status', 'active')
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->first(),
            'attendanceCountToday' => Attendance::whereDate('attended_at', today())->count(),
        ]);
    }
}
