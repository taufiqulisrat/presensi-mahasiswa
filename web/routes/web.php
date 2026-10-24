<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceSessionController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FaceEnrollmentController;
use App\Http\Controllers\StudentController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', [AttendanceController::class, 'landing'])->name('home');
Route::get('/mulai-absen', [AttendanceController::class, 'create'])->name('attendance.create');
Route::post('/mulai-absen/liveness', [AttendanceController::class, 'startLiveness'])
    ->middleware('throttle:20,1')
    ->name('attendance.liveness.start');
Route::post('/mulai-absen/liveness/frame', [AttendanceController::class, 'processLivenessFrame'])
    ->middleware('throttle:60,1')
    ->name('attendance.liveness.frame');
Route::post('/mulai-absen/selesai', [AttendanceController::class, 'complete'])
    ->middleware('throttle:10,1')
    ->name('attendance.complete');

Route::middleware('guest')->group(function (): void {
    Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('login');
    Route::post('/admin/login', [AdminAuthController::class, 'store'])->name('admin.login.store');
});

Route::middleware(['auth', EnsureAdmin::class])->prefix('admin')->as('admin.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
    Route::get('/password', [AdminAuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/password', [AdminAuthController::class, 'updatePassword'])->name('password.update');
    Route::resource('students', StudentController::class);
    Route::get('/students/{student}/face-enrollment', [FaceEnrollmentController::class, 'create'])->name('students.enrollment.create');
    Route::post('/students/{student}/face-enrollment', [FaceEnrollmentController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('students.enrollment.store');
    Route::post('/students/{student}/face-enrollment/reset', [FaceEnrollmentController::class, 'reset'])
        ->name('students.enrollment.reset');
    Route::resource('courses', CourseController::class);
    Route::resource('classrooms', ClassroomController::class);
    Route::resource('attendance-sessions', AttendanceSessionController::class);
    Route::post('/attendance-sessions/{attendanceSession}/open', [AttendanceSessionController::class, 'open'])->name('attendance-sessions.open');
    Route::post('/attendance-sessions/{attendanceSession}/close', [AttendanceSessionController::class, 'close'])->name('attendance-sessions.close');
    Route::get('/reports', [AttendanceController::class, 'report'])->name('reports.index');
});
