<x-layouts.app>
    <section class="auth-card">
        <p class="eyebrow">AREA TERLINDUNGI</p>
        <h1>Login Admin</h1>
        <p>Masuk untuk mengelola mahasiswa, pendaftaran wajah, sesi, dan laporan presensi.</p>

        <form method="POST" action="{{ route('admin.login.store') }}" class="form-grid">
            @csrf
            <label>
                Email
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            </label>
            <label>
                Kata sandi
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <label class="checkbox-label">
                <input type="checkbox" name="remember" value="1">
                Ingat saya di komputer ini
            </label>
            <button class="button button-primary" type="submit">Masuk ke Dashboard</button>
        </form>
    </section>
</x-layouts.app>
