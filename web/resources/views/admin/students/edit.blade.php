<x-layouts.app>
    <div class="page-heading"><div><p class="eyebrow">DATA MASTER</p><h1>Ubah Mahasiswa</h1></div><a class="button button-secondary" href="{{ route('admin.students.enrollment.create', $student) }}">Pendaftaran Wajah</a></div>
    <form method="POST" action="{{ route('admin.students.update', $student) }}" class="content-card form-card">@csrf @method('PUT') @include('admin.students._form')</form>
</x-layouts.app>
