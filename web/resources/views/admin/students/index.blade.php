<x-layouts.app>
    <div class="page-heading">
        <div>
            <p class="eyebrow">DATA MASTER</p>
            <h1>Mahasiswa</h1>
        </div>
        <a class="button button-primary" href="{{ route('admin.students.create') }}">Tambah Mahasiswa</a>
    </div>

    <section class="content-card table-card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>NIM</th><th>Nama</th><th>Kelas</th><th>Wajah</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr>
                            <td>{{ $student->nim }}</td>
                            <td><strong>{{ $student->name }}</strong><small>{{ $student->email }}</small></td>
                            <td>{{ $student->classrooms_count }} kelas</td>
                            <td><span class="status-pill {{ $student->is_face_enrolled ? 'status-success' : 'status-draft' }}">{{ $student->is_face_enrolled ? 'Terdaftar' : 'Belum' }}</span></td>
                            <td class="table-actions">
                                @if ($student->is_face_enrolled)
                                    <form method="POST" action="{{ route('admin.students.enrollment.reset', $student) }}" onsubmit="return confirm('Hapus data wajah lama mahasiswa ini dan mulai pendaftaran ulang?')">
                                        @csrf
                                        <button class="button button-secondary" type="submit">Daftarkan Ulang Wajah</button>
                                    </form>
                                @else
                                    <a class="button button-secondary" href="{{ route('admin.students.enrollment.create', $student) }}">Daftar Wajah</a>
                                @endif
                                <a class="button button-quiet" href="{{ route('admin.students.edit', $student) }}">Ubah</a>
                                <form method="POST" action="{{ route('admin.students.destroy', $student) }}" onsubmit="return confirm('Hapus mahasiswa ini beserta data wajahnya?')">
                                    @csrf @method('DELETE')
                                    <button class="button button-quiet" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell">Belum ada mahasiswa. Tambahkan data terlebih dahulu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $students->links() }}</div>
    </section>
</x-layouts.app>
