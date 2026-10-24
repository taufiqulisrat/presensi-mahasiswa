<x-layouts.app>
    <div class="page-heading">
        <div><p class="eyebrow">BIOMETRIK</p><h1>Pendaftaran Wajah</h1><p class="muted">{{ $student->nim }} · {{ $student->name }}</p></div>
        <a class="button button-secondary" href="{{ route('admin.students.index') }}">Kembali</a>
    </div>

    <section class="enrollment-layout" data-enrollment
        data-endpoint="{{ route('admin.students.enrollment.store', $student) }}"
        data-complete-url="{{ route('admin.students.index') }}"
        data-enrollment-complete="{{ $student->is_face_enrolled ? 'true' : 'false' }}">
        <div class="camera-card">
            <video class="camera-preview" data-camera autoplay muted playsinline aria-label="Pratinjau kamera"></video>
            <canvas data-canvas hidden></canvas>
            <div class="camera-status" data-camera-status>Menyiapkan kamera…</div>
        </div>
        <aside class="content-card">
            <p class="eyebrow">LANGKAH <span data-pose-number>1</span> DARI 5</p>
            <h2 data-pose-label>Hadap lurus ke kamera</h2>
            <p class="muted" data-enrollment-message>Pastikan satu wajah terlihat jelas, pencahayaan cukup, lalu ambil foto.</p>
            <ol class="pose-progress" data-pose-progress>
                <li data-pose="front" class="current">Depan</li><li data-pose="left">Kiri</li><li data-pose="right">Kanan</li><li data-pose="up">Atas</li><li data-pose="down">Bawah</li>
            </ol>
            <button class="button button-primary button-large" data-capture type="button" disabled>Ambil Pose</button>
        </aside>
    </section>
    <script src="{{ asset('js/enrollment.js') }}" defer></script>
</x-layouts.app>
