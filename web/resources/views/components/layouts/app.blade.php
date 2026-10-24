<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Presensi Aman' }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site-header">
        <nav class="nav container" aria-label="Navigasi utama">
            <a class="brand" href="{{ route('home') }}">Presensi<span>Aman</span></a>

            @auth
            <button class="nav-toggle" type="button" aria-label="Buka menu" aria-expanded="false" aria-controls="nav-links" data-nav-toggle><span></span></button>
            @endauth

            <div class="nav-links @guest nav-links-static @endguest" id="nav-links" data-nav-links>
                @auth
                    <a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>Dashboard</a>
                    <a href="{{ route('admin.students.index') }}" @if (request()->routeIs('admin.students.*')) aria-current="page" @endif>Mahasiswa</a>
                    <a href="{{ route('admin.attendance-sessions.index') }}" @if (request()->routeIs('admin.attendance-sessions.*')) aria-current="page" @endif>Sesi</a>
                    <a href="{{ route('admin.reports.index') }}" @if (request()->routeIs('admin.reports.*')) aria-current="page" @endif>Laporan</a>
                    <a href="{{ route('admin.password.edit') }}" @if (request()->routeIs('admin.password.*')) aria-current="page" @endif>Password</a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="button button-quiet" type="submit">Keluar</button>
                    </form>
                @else
                    <a href="{{ route('login') }}">Login Admin</a>
                @endauth
            </div>
        </nav>
    </header>

    <main class="container page-content">
        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <strong>Ada data yang perlu diperbaiki.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>

    <script>
        (() => {
            const toggle = document.querySelector('[data-nav-toggle]');
            const links = document.querySelector('[data-nav-links]');

            if (!toggle || !links) {
                return;
            }

            const setOpen = (open) => {
                links.classList.toggle('is-open', open);
                toggle.setAttribute('aria-expanded', String(open));
                toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
            };

            toggle.addEventListener('click', () => setOpen(!links.classList.contains('is-open')));
            document.addEventListener('click', (event) => {
                if (!toggle.contains(event.target) && !links.contains(event.target)) {
                    setOpen(false);
                }
            });
            document.addEventListener('keydown', (event) => event.key === 'Escape' && setOpen(false));
        })();
    </script>
</body>
</html>
