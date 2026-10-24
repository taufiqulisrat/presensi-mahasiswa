<x-layouts.app>
    <div class="page-heading"><div><p class="eyebrow">DATA MASTER</p><h1>Mata Kuliah</h1></div><a class="button button-primary" href="{{ route('admin.courses.create') }}">Tambah Mata Kuliah</a></div>
    <section class="content-card table-card"><div class="table-wrap"><table><thead><tr><th>Kode</th><th>Nama</th><th>SKS</th><th>Aksi</th></tr></thead><tbody>
        @forelse ($courses as $course)<tr><td>{{ $course->code }}</td><td><strong>{{ $course->name }}</strong></td><td>{{ $course->credits }}</td><td class="table-actions"><a class="button button-quiet" href="{{ route('admin.courses.edit', $course) }}">Ubah</a><form method="POST" action="{{ route('admin.courses.destroy', $course) }}" onsubmit="return confirm('Hapus mata kuliah ini?')">@csrf @method('DELETE')<button class="button button-quiet" type="submit">Hapus</button></form></td></tr>
        @empty<tr><td colspan="4" class="empty-cell">Belum ada mata kuliah.</td></tr>@endforelse
    </tbody></table></div><div class="pagination">{{ $courses->links() }}</div></section>
</x-layouts.app>
