<x-layouts.app>
    <div class="page-heading"><div><p class="eyebrow">REKAPITULASI</p><h1>Laporan Presensi</h1></div></div>
    <section class="content-card table-card">
        <form class="filter-form" method="GET" action="{{ route('admin.reports.index') }}"><label>Sesi<select name="attendance_session_id" onchange="this.form.submit()"><option value="">Semua sesi</option>@foreach ($attendanceSessions as $attendanceSession)<option value="{{ $attendanceSession->id }}" @selected((string) request('attendance_session_id') === (string) $attendanceSession->id)>{{ $attendanceSession->starts_at->format('d M H:i') }} · {{ $attendanceSession->course->code }} · {{ $attendanceSession->classroom->code }}</option>@endforeach</select></label><noscript><button class="button button-secondary" type="submit">Terapkan</button></noscript></form>
        <div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Mahasiswa</th><th>Sesi</th><th>Jarak</th><th>Status</th></tr></thead><tbody>
            @forelse ($attendances as $attendance)<tr><td>{{ $attendance->attended_at->format('d M Y H:i:s') }}</td><td><strong>{{ $attendance->student->name }}</strong><small>{{ $attendance->student->nim }}</small></td><td>{{ $attendance->attendanceSession->course->code }} · {{ $attendance->attendanceSession->classroom->code }}</td><td>{{ number_format($attendance->similarity, 4) }}</td><td><span class="status-pill status-success">Hadir</span></td></tr>
            @empty<tr><td colspan="5" class="empty-cell">Belum ada catatan presensi pada filter ini.</td></tr>@endforelse
        </tbody></table></div><div class="pagination">{{ $attendances->links() }}</div>
    </section>
</x-layouts.app>
