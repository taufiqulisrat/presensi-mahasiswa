<?php

namespace App\Models;

use Database\Factories\LivenessAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attendance_session_id', 'student_id', 'ai_session_id', 'challenges', 'passed', 'failure_reason', 'completed_at'])]
class LivenessAttempt extends Model
{
    /** @use HasFactory<LivenessAttemptFactory> */
    use HasFactory;

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    protected function casts(): array
    {
        return [
            'challenges' => 'array',
            'passed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }
}
