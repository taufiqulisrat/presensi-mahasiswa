<x-layouts.app>
    <div class="page-heading"><div><p class="eyebrow">DATA MASTER</p><h1>Kelas</h1></div><a class="button button-primary" href="{{ route('admin.classrooms.create') }}">Tambah Kelas</a></div>
    <section class="content-card table-card"><div class="table-wrap"><table><thead><tr><th>Kode</th><th>Nama</th><th>Periode</th><th>Mahasiswa</th><th>Aksi</th></tr></thead><tbody>
        @forelse ($classrooms as $classroom)<tr><td>{{ $classroom->code }}</td><td><strong>{{ $classroom->name }}</strong></td><td>{{ $classroom->academic_year }} · Semester {{ $classroom->semester }}</td><td>{{ $classroom->students_count }}</td><td class="table-actions"><a class="button button-quiet" href="{{ route('admin.classrooms.edit', $classroom) }}">Ubah</a><form method="POST" action="{{ route('admin.classrooms.destroy', $classroom) }}" onsubmit="return confirm('Hapus kelas ini?')">@csrf @method('DELETE')<button class="button button-quiet" type="submit">Hapus</button></form></td></tr>
        @empty<tr><td colspan="5" class="empty-cell">Belum ada kelas.</td></tr>@endforelse
    </tbody></table></div><div class="pagination">{{ $classrooms->links() }}</div></section>
</x-layouts.app>
