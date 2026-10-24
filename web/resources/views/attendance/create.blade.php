<x-layouts.app>
    <section class="attendance-layout" data-attendance
        data-start-url="{{ route('attendance.liveness.start') }}"
        data-frame-url="{{ route('attendance.liveness.frame') }}"
        data-complete-url="{{ route('attendance.complete') }}"
        data-home-url="{{ route('home') }}">
        <div>
            <p class="eyebrow">SESI AKTIF</p>
            <h1>Mulai Presensi</h1>
            <p class="lead">{{ $attendanceSession->course->code }} · {{ $attendanceSession->course->name }}<br>{{ $attendanceSession->classroom->code }} · {{ $attendanceSession->classroom->name }}</p>
            <div class="camera-card">
                <video class="camera-preview" data-camera autoplay muted playsinline aria-label="Pratinjau kamera"></video>
                <canvas data-canvas hidden></canvas>
                <div class="camera-status" data-camera-status>Menyiapkan kamera…</div>
            </div>
        </div>
        <aside class="challenge-card">
            <p class="eyebrow">VERIFIKASI KEHADIRAN</p>
            <span class="challenge-step" data-challenge-step>Siap memulai</span>
            <h2 data-instruction>Posisikan wajah di tengah kamera.</h2>
            <p class="challenge-timer" data-challenge-timer aria-live="polite">Menunggu verifikasi dimulai</p>
            <p class="muted" data-message>Kamera akan mengambil frame secara otomatis setelah Anda mulai.</p>
            <button type="button" class="button button-primary button-large" data-start disabled>Mulai Verifikasi</button>
            <button type="button" class="button button-secondary" data-voice-toggle>Suara: Aktif</button>
            <a class="button button-secondary" href="{{ route('home') }}">Batal</a>
        </aside>
    </section>
    <script src="{{ asset('js/attendance.js') }}" defer></script>
</x-layouts.app>
