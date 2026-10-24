<div class="field-grid">
    <label>Kode kelas<input name="code" value="{{ old('code', $classroom?->code) }}" maxlength="50" required></label>
    <label>Nama kelas<input name="name" value="{{ old('name', $classroom?->name) }}" maxlength="255" required></label>
    <label>Tahun akademik<input name="academic_year" value="{{ old('academic_year', $classroom?->academic_year ?? '2026/2027') }}" placeholder="2026/2027" pattern="\d{4}/\d{4}" required></label>
    <label>Semester<input type="number" name="semester" min="1" max="14" value="{{ old('semester', $classroom?->semester ?? 1) }}" required></label>
</div>
<div class="form-actions"><a class="button button-secondary" href="{{ route('admin.classrooms.index') }}">Batal</a><button class="button button-primary" type="submit">Simpan Kelas</button></div>
