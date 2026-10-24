<div class="field-grid">
    <label>NIM<input name="nim" value="{{ old('nim', $student?->nim) }}" maxlength="30" required></label>
    <label>Nama lengkap<input name="name" value="{{ old('name', $student?->name) }}" maxlength="255" required></label>
    <label>Email (opsional)<input type="email" name="email" value="{{ old('email', $student?->email) }}" maxlength="255"></label>
    <label>Kelas
        @php($selectedClassrooms = old('classroom_ids', $student?->classrooms->modelKeys() ?? []))
        <select name="classroom_ids[]" multiple size="5" aria-describedby="classroom-help">
            @foreach ($classrooms as $classroom)
                <option value="{{ $classroom->id }}" @selected(in_array($classroom->id, $selectedClassrooms))>{{ $classroom->code }} · {{ $classroom->name }}</option>
            @endforeach
        </select>
        <small id="classroom-help">Tekan Ctrl atau Cmd untuk memilih lebih dari satu kelas.</small>
    </label>
</div>
<div class="form-actions"><a class="button button-secondary" href="{{ route('admin.students.index') }}">Batal</a><button class="button button-primary" type="submit">Simpan Mahasiswa</button></div>
