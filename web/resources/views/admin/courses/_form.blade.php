<div class="field-grid">
    <label>Kode mata kuliah<input name="code" value="{{ old('code', $course?->code) }}" maxlength="30" required></label>
    <label>Nama mata kuliah<input name="name" value="{{ old('name', $course?->name) }}" maxlength="255" required></label>
    <label>SKS<input type="number" name="credits" min="1" max="8" value="{{ old('credits', $course?->credits ?? 3) }}" required></label>
</div>
<div class="form-actions"><a class="button button-secondary" href="{{ route('admin.courses.index') }}">Batal</a><button class="button button-primary" type="submit">Simpan Mata Kuliah</button></div>
