<x-layouts.app>
    <div class="page-heading"><div><p class="eyebrow">DATA MASTER</p><h1>Tambah Mahasiswa</h1></div></div>
    <form method="POST" action="{{ route('admin.students.store') }}" class="content-card form-card">@csrf @include('admin.students._form', ['student' => null])</form>
</x-layouts.app>
