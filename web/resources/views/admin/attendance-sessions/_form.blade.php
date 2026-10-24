<div class="field-grid">
    <label>Kelas<select name="classroom_id" required><option value="">Pilih kelas</option>@foreach ($classrooms as $classroom)<option value="{{ $classroom->id }}" @selected((string) old('classroom_id', $attendanceSession?->classroom_id) === (string) $classroom->id)>{{ $classroom->code }} · {{ $classroom->name }}</option>@endforeach</select></label>
    <label>Mata kuliah<select name="course_id" required><option value="">Pilih mata kuliah</option>@foreach ($courses as $course)<option value="{{ $course->id }}" @selected((string) old('course_id', $attendanceSession?->course_id) === (string) $course->id)>{{ $course->code }} · {{ $course->name }}</option>@endforeach</select></label>
    <label>Mulai<input type="datetime-local" name="starts_at" value="{{ old('starts_at', $attendanceSession?->starts_at?->format('Y-m-d\\TH:i') ?? now()->format('Y-m-d\\TH:i')) }}" required></label>
    <label>Selesai<input type="datetime-local" name="ends_at" value="{{ old('ends_at', $attendanceSession?->ends_at?->format('Y-m-d\\TH:i') ?? now()->addHours(2)->format('Y-m-d\\TH:i')) }}" required></label>
    <label class="field-wide">Catatan (opsional)<textarea name="notes" rows="3" maxlength="500">{{ old('notes', $attendanceSession?->notes) }}</textarea></label>
</div>
<div class="form-actions"><a class="button button-secondary" href="{{ route('admin.attendance-sessions.index') }}">Batal</a><button class="button button-primary" type="submit">Simpan Sesi</button></div>
