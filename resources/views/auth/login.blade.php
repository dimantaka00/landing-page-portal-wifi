<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>Masuk admin · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('assets/favicon.svg') }}" type="image/svg+xml">
    <link rel="preload" href="{{ asset('assets/fonts/plus-jakarta-sans.ttf') }}" as="font" type="font/ttf" crossorigin>
    <link rel="stylesheet" href="{{ asset('assets/portal.css') }}"><link rel="stylesheet" href="{{ asset('assets/modern.css') }}?v={{ filemtime(public_path('assets/modern.css')) }}">
    <script src="{{ asset('assets/portal.js') }}?v={{ filemtime(public_path('assets/portal.js')) }}" defer></script>
<link rel="stylesheet" href="{{ asset('assets/vercel.css') }}?v={{ filemtime(public_path('assets/vercel.css')) }}">
</head>
<body class="login-body">
<main class="login-shell">
    <section class="login-intro"><x-brand /><div class="login-story"><span class="hero-badge"><x-icon name="building" /> RUANG PENGELOLA INFORMASI</span><h1>Di balik setiap kabar,<br>ada <em>peran Anda.</em></h1><p>Hadirkan informasi yang membantu rekan kantor terhubung, terinformasi, dan siap beraktivitas.</p><div class="login-features"><span><x-icon name="file" />Tulis & kelola pengumuman</span><span><x-icon name="image" />Bagikan foto kegiatan</span><span><x-icon name="calendar" />Atur waktu publikasi</span></div></div><span class="login-footnote">{{ config('app.name') }} · {{ now()->year }}</span></section>
    <section class="login-form-panel"><div class="login-box"><span class="login-lock"><x-icon name="lock" /></span><span class="login-eyebrow">KHUSUS ADMINISTRATOR</span><h2>Selamat datang<br>kembali.</h2><p class="muted">Masuk untuk mengelola kabar terbaru kantor.</p>
        @if($errors->any())<div class="notice notice-error" role="alert">{{ $errors->first() }}</div>@endif
        <form action="{{ route('login.store') }}" method="post" class="stack-form">@csrf<label for="username">Username admin</label><input id="username" name="username" type="text" value="{{ old('username') }}" placeholder="admin" required autocomplete="username" maxlength="254" autofocus><label for="password">Kata sandi</label><div class="password-field"><input id="password" name="password" type="password" required autocomplete="current-password" maxlength="1024"><button class="password-toggle" type="button" data-toggle-password aria-label="Tampilkan kata sandi" aria-pressed="false"><x-icon name="eye" /></button></div><button class="button button-primary button-full" type="submit">Masuk ke dashboard</button></form>
        <label class="theme-control"><span>Tema tampilan</span><select data-theme-select aria-label="Tema tampilan"><option value="system">Sistem</option><option value="light">Terang</option><option value="dark">Gelap</option></select></label><p class="login-help"><x-icon name="shield" />Akses khusus administrator yang berwenang.</p><a class="login-back" href="{{ route('home') }}">Kembali ke halaman publik</a>
    </div></section>
</main>
</body>
</html>