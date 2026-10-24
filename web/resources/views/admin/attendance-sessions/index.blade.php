<x-layouts.app>
    <div class="page-heading"><div><p class="eyebrow">OPERASIONAL</p><h1>Sesi Presensi</h1></div><a class="button button-primary" href="{{ route('admin.attendance-sessions.create') }}">Buat Sesi</a></div>
    <section class="content-card table-card"><div class="table-wrap"><table><thead><tr><th>Mata kuliah / kelas</th><th>Jadwal</th><th>Status</th><th>Hadir</th><th>Aksi</th></tr></thead><tbody>
        @forelse ($attendanceSessions as $attendanceSession)
            <tr><td><strong>{{ $attendanceSession->course->code }}</strong><small>{{ $attendanceSession->classroom->code }} · {{ $attendanceSession->classroom->name }}</small></td><td>{{ $attendanceSession->starts_at->format('d M Y H:i') }}<small>s.d. {{ $attendanceSession->ends_at->format('H:i') }}</small></td><td><span class="status-pill status-{{ $attendanceSession->status }}">{{ ucfirst($attendanceSession->status) }}</span></td><td>{{ $attendanceSession->attendances_count }}</td><td class="table-actions">
                @if ($attendanceSession->status !== 'active')<form method="POST" action="{{ route('admin.attendance-sessions.open', $attendanceSession) }}">@csrf<button class="button button-primary" type="submit">Buka</button></form>@else<form method="POST" action="{{ route('admin.attendance-sessions.close', $attendanceSession) }}">@csrf<button class="button button-secondary" type="submit">Tutup</button></form>@endif
                <a class="button button-quiet" href="{{ route('admin.attendance-sessions.edit', $attendanceSession) }}">Ubah</a>
                @if ($attendanceSession->status !== 'active')<form method="POST" action="{{ route('admin.attendance-sessions.destroy', $attendanceSession) }}" onsubmit="return confirm('Hapus sesi ini?')">@csrf @method('DELETE')<button class="button button-quiet" type="submit">Hapus</button></form>@endif
            </td></tr>
        @empty<tr><td colspan="5" class="empty-cell">Belum ada sesi presensi.</td></tr>@endforelse
    </tbody></table></div><div class="pagination">{{ $attendanceSessions->links() }}</div></section>
</x-layouts.app>
