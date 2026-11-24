<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Admin') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('assets/favicon.svg') }}" type="image/svg+xml">
    <link rel="preload" href="{{ asset('assets/fonts/plus-jakarta-sans.ttf') }}" as="font" type="font/ttf" crossorigin>
    <link rel="stylesheet" href="{{ asset('assets/portal.css') }}"><link rel="stylesheet" href="{{ asset('assets/modern.css') }}?v={{ filemtime(public_path('assets/modern.css')) }}">
    <script src="{{ asset('assets/portal.js') }}?v={{ filemtime(public_path('assets/portal.js')) }}" defer></script>
<link rel="stylesheet" href="{{ asset('assets/vercel.css') }}?v={{ filemtime(public_path('assets/vercel.css')) }}">
</head>
<body class="admin-body">
<a class="skip-link" href="#main">Lewati ke konten utama</a>
<aside class="admin-sidebar">
    <x-brand />
    <div class="sidebar-label">RUANG KERJA ANDA</div>
    <nav aria-label="Navigasi admin"><a class="sidebar-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}" href="{{ route('admin.announcements.index') }}"><x-icon name="bell" />Pengumuman<span class="nav-active-marker"></span></a><a class="sidebar-link {{ request()->routeIs('admin.deletion-logs.*') ? 'active' : '' }}" href="{{ route('admin.deletion-logs.index') }}"><x-icon name="clock" />Log penghapusan</a>@if(auth()->user()->can_manage_users)
<a class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><x-icon name="users" />Pengaturan pengguna</a>
@endif
<a class="sidebar-link" href="{{ route('home') }}" target="_blank" rel="noopener"><x-icon name="eye" />Lihat halaman publik</a></nav>
    <div class="sidebar-message"><span><x-icon name="shield" /></span><strong>Informasi yang berarti,<br>dimulai dari Anda.</strong><p>Bagikan kabar yang membantu rekan kantor tetap terinformasi.</p></div>
    <div class="sidebar-bottom"><span class="sidebar-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><div><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->can_manage_users ? 'Administrator kantor' : 'Pengelola pengumuman' }}</span></div></div>
</aside>
<div class="admin-workspace">
    <header class="admin-topbar"><span class="admin-breadcrumb"><x-icon name="building" />Portal kantor<span class="divider">/</span><strong>Ruang admin</strong></span><div><label class="theme-control"><span>Tema tampilan</span><select data-theme-select aria-label="Tema tampilan"><option value="system">Sistem</option><option value="light">Terang</option><option value="dark">Gelap</option></select></label><span class="admin-date"><x-icon name="calendar" />{{ now()->translatedFormat('d M Y') }}</span><form action="{{ route('admin.logout') }}" method="post">@csrf<button class="button button-quiet" type="submit"><x-icon name="logout" />Keluar</button></form></div></header>
    <main id="main" class="admin-main">
        @if(session('success'))<div class="notice notice-success" role="status"><x-icon name="check" />{{ session('success') }}</div>@endif
        @if($errors->any())<div class="notice notice-error" role="alert"><strong>Periksa kembali data berikut:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
    <footer class="admin-footer"><span>{{ config('app.name') }}</span><span>{{ config('portal.office') }} · Ruang pengelola</span></footer>
</div>
</body>
</html>