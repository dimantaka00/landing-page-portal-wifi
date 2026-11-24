<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Pengumuman dan informasi terbaru untuk seluruh pegawai dan tamu kantor.">
    <title>@yield('title', 'Beranda') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('assets/favicon.svg') }}" type="image/svg+xml">
    <link rel="preload" href="{{ asset('assets/fonts/plus-jakarta-sans.ttf') }}" as="font" type="font/ttf" crossorigin>
    <link rel="stylesheet" href="{{ asset('assets/portal.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/modern.css') }}?v={{ filemtime(public_path('assets/modern.css')) }}">
    <script src="{{ asset('assets/portal.js') }}?v={{ filemtime(public_path('assets/portal.js')) }}" defer></script>
<link rel="stylesheet" href="{{ asset('assets/vercel.css') }}?v={{ filemtime(public_path('assets/vercel.css')) }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}"><meta name="theme-color" content="#111111">
    <script src="{{ asset('assets/notifications.js') }}?v={{ filemtime(public_path('assets/notifications.js')) }}" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Lewati ke konten utama</a>
<div class="topbar"><div class="container topbar-inner"><span><x-icon name="building" /> Ruang informasi pegawai & tamu kantor</span><span>{{ now()->translatedFormat('l, d F Y') }} <span class="wib">WIB</span></span></div></div>
<header class="public-header">
    <div class="container header-inner">
        <x-brand />
        <nav aria-label="Navigasi utama">
            <a class="nav-link {{ request()->routeIs('home', 'portal') ? 'active' : '' }}" href="{{ route('home') }}">Beranda</a>
            <a class="nav-link" href="{{ route('home') }}#pengumuman">Pengumuman</a>
            <details class="portal-menu"><summary>Kategori</summary><div class="portal-menu-panel"><a href="{{ route('home') }}#pengumuman">Semua pengumuman</a>@foreach(\App\Models\Announcement::CATEGORIES as $menuCategory)<a href="{{ route('home', ['category' => $menuCategory]) }}#pengumuman">@if($menuCategory === 'Maintenance')<x-icon name="maintenance" />@endif{{ $menuCategory }}</a>@endforeach</div></details><a class="admin-link" href="{{ route('admin.dashboard') }}"><x-icon name="lock" /><span>Admin</span></a>
        </nav>
    </div>
</header>
<section class="container notification-optin" data-notification-center data-initial-popup="{{ request()->routeIs('portal') ? 'true' : 'false' }}" data-feed-url="{{ route('notifications.feed') }}" data-subscribe-url="{{ route('notifications.subscribe') }}" data-worker-url="{{ asset('service-worker.js') }}" data-poll-seconds="{{ config('webpush.poll_seconds') }}">
    <div><strong><x-icon name="bell" /> Tetap terhubung dengan kabar kantor</strong><p data-notification-status role="status">Pop-up pembaruan aktif selama portal terbuka.</p></div>
    <div class="notification-optin-actions"><button class="button button-outline" type="button" data-push-enable hidden>Aktifkan notifikasi perangkat</button><button class="button button-quiet" type="button" data-push-disable hidden>Nonaktifkan notifikasi perangkat</button></div>
    <small data-push-help>Untuk notifikasi setelah portal ditutup, buka portal melalui browser utama dan izinkan notifikasi. Pada iPhone/iPad, tambahkan portal ke Layar Utama terlebih dahulu.</small>
</section>
<aside class="portal-update-stack" data-update-stack aria-label="Pembaruan pengumuman" hidden></aside>
<main id="main">@yield('content')</main>
<footer class="site-footer">
    <div class="container footer-content">
        <div class="footer-brand"><x-brand /><p>Satu ruang untuk informasi yang berarti.<br>Terhubung dengan kabar terbaru di kantor Anda.</p></div>
        <div class="footer-links"><strong>Jelajahi portal</strong><a href="{{ route('home') }}">Beranda</a><a href="{{ route('home') }}#pengumuman">Pengumuman kantor</a><a href="{{ route('admin.dashboard') }}">Ruang admin</a></div>
        <div class="footer-help"><span class="footer-help-icon"><x-icon name="shield" /></span><strong>Butuh bantuan?</strong><p>Hubungi tim TI Pusdatin.</p>@if(config('portal.help_email'))<a href="mailto:{{ config('portal.help_email') }}">{{ config('portal.help_email') }}</a>@endif</div>
    </div>
    <div class="container footer-bottom"><label class="theme-control"><span>Tema tampilan</span><select data-theme-select aria-label="Tema tampilan"><option value="system">Sistem</option><option value="light">Terang</option><option value="dark">Gelap</option></select></label><span>© {{ now()->year }} {{ config('portal.office') }}. Portal informasi Kantor.</span><span><x-icon name="building" /> Developer by Daniel Christper I.P</span></div>
</footer>
</body>
</html>