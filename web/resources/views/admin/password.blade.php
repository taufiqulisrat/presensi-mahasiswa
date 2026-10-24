<x-layouts.app>
    <div class="page-heading"><div><p class="eyebrow">KEAMANAN AKUN</p><h1>Ubah Kata Sandi</h1></div></div>
    <form method="POST" action="{{ route('admin.password.update') }}" class="content-card form-card narrow-form">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <label>Kata sandi saat ini<input type="password" name="current_password" autocomplete="current-password" required></label>
            <label>Kata sandi baru<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
            <label>Konfirmasi kata sandi baru<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
        </div>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('admin.dashboard') }}">Batal</a><button class="button button-primary" type="submit">Simpan Kata Sandi</button></div>
    </form>
</x-layouts.app>
