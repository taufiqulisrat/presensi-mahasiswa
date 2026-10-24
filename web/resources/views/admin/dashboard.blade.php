<x-layouts.app>
    <div class="page-heading">
        <div>
            <p class="eyebrow">DASHBOARD ADMIN</p>
            <h1>Ringkasan presensi</h1>
        </div>
        <a class="button button-primary" href="{{ route('admin.attendance-sessions.create') }}">Buat Sesi</a>
    </div>

    <section class="stat-grid">
        <article class="stat-card">
            <span>Mahasiswa</span>
            <strong>{{ $studentCount }}</strong>
            <small>{{ $enrolledStudentCount }} wajah terdaftar</small>
        </article>
        <article class="stat-card">
            <span>Presensi hari ini</span>
            <strong>{{ $attendanceCountToday }}</strong>
            <small>catatan kehadiran</small>
        </article>
        <article class="stat-card">
            <span>Status sesi</span>
            <strong>{{ $activeSession ? 'Aktif' : 'Tidak ada' }}</strong>
            <small>{{ $activeSession ? $activeSession->course->code.' · '.$activeSession->classroom->code : 'Buka sesi saat kelas dimulai' }}</small>
        </article>
    </section>

    <section class="content-card">
        <h2>Menu cepat</h2>
        <div class="action-grid">
            <a href="{{ route('admin.students.index') }}">Kelola mahasiswa <span>→</span></a>
            <a href="{{ route('admin.classrooms.index') }}">Atur kelas <span>→</span></a>
            <a href="{{ route('admin.courses.index') }}">Atur mata kuliah <span>→</span></a>
            <a href="{{ route('admin.reports.index') }}">Lihat laporan <span>→</span></a>
        </div>
    </section>
</x-layouts.app>
