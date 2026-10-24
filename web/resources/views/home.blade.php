<x-layouts.app>
    <section class="hero">
        <div>
            <p class="eyebrow">PRESENSI BERBASIS WAJAH</p>
            <h1>Hadir secara nyata, tercatat dengan aman.</h1>
            <p class="lead">Presensi menggunakan verifikasi wajah dan challenge acak untuk membantu mencegah titip absen dengan foto atau video.</p>

            @if ($attendanceSession)
                <div class="session-summary">
                    <span class="status-pill status-active">Sesi aktif</span>
                    <strong>{{ $attendanceSession->course->code }} · {{ $attendanceSession->course->name }}</strong>
                    <span>{{ $attendanceSession->classroom->name }} ({{ $attendanceSession->classroom->code }})</span>
                    <span>sampai {{ $attendanceSession->ends_at->format('H:i') }} WIB</span>
                </div>
                <a class="button button-primary button-large" href="{{ route('attendance.create') }}">Mulai Absen</a>
            @else
                <div class="session-summary session-summary-muted">
                    <strong>Belum ada sesi presensi aktif</strong>
                    <span>Silakan tunggu admin membuka sesi untuk kelas Anda.</span>
                </div>
            @endif
        </div>

        <aside class="hero-card">
            <div class="hero-icon" aria-hidden="true">◉</div>
            <h2>Tanpa login mahasiswa</h2>
            <p>Mahasiswa cukup menatap kamera, mengikuti instruksi, lalu sistem memverifikasi kehadirannya.</p>
            <ul class="check-list">
                <li>Challenge gerakan acak</li>
                <li>Deteksi kedipan mata</li>
                <li>Pengenalan FaceNet</li>
            </ul>
        </aside>
    </section>
</x-layouts.app>
