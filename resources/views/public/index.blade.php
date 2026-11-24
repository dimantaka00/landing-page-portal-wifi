@extends('layouts.public')
@section('title', 'Informasi & Pengumuman')
@section('content')
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="hero-badge"><x-icon name="building" /> RUANG INFORMASI UNTUK KITA SEMUA</span>
            <h1>Informasi kantor.<br><em>Dalam satu platform.</em></h1>
            <p>Pengumuman penting, agenda kegiatan, dan kabar terbaru. Semuanya terhubung dalam satu tempat.</p>
            <div class="hero-actions"><a class="button button-gold" href="#pengumuman"><x-icon name="bell" />Jelajahi pengumuman</a><a class="button button-outline" href="{{ route('admin.dashboard') }}">Buka dashboard</a></div>
            <div class="hero-note"><span class="hero-note-icon"><x-icon name="shield" /></span><span>Langsung dari administrator kantor</span></div>
        </div>
        <div class="hero-visual">
            <section class="ministry-carousel" data-ministry-carousel role="region" aria-roledescription="carousel" aria-label="Galeri Kementerian Sosial">
                <div class="ministry-slides">
                    <figure class="ministry-slide" data-ministry-slide role="group" aria-roledescription="slide" aria-label="1 dari 2">
                        <img src="{{ asset('assets/kemensos/salemba.jpg') }}" alt="Gedung Kementerian Sosial RI di Salemba dengan bendera Merah Putih" width="1024" height="768" fetchpriority="high">
                        <figcaption><span>KEMENTERIAN SOSIAL RI</span><strong>Salemba, Jakarta</strong><a href="https://uinjkt.ac.id/id/kemensos-ri-uin-jakarta-jajaki-kerjasama" target="_blank" rel="noopener noreferrer">Foto: UIN Syarif Hidayatullah Jakarta</a></figcaption>
                    </figure>
                    <figure class="ministry-slide" data-ministry-slide role="group" aria-roledescription="slide" aria-label="2 dari 2" hidden>
                        <img src="{{ asset('assets/kemensos/cawang.jpg') }}" alt="Gedung Cawang Kencana Kementerian Sosial dengan taman di bagian depan" width="621" height="421">
                        <figcaption><span>KEMENTERIAN SOSIAL RI</span><strong>Cawang Kencana, Jakarta</strong><a href="https://www.idntimes.com/news/indonesia/kembalinya-gedung-cawang-kencana-csc-1-02-1kg27-mcdfnh" target="_blank" rel="noopener noreferrer">Foto: Kemensos melalui IDN Times</a></figcaption>
                    </figure>
                </div>
                <div class="ministry-controls" data-carousel-controls hidden>
                    <div class="ministry-dots" aria-label="Pilih foto"><button type="button" data-slide-to="0" aria-label="Tampilkan foto Salemba" aria-current="true"></button><button type="button" data-slide-to="1" aria-label="Tampilkan foto Cawang"></button></div>
                    <div class="ministry-navigation"><button type="button" data-slide-prev>Sebelumnya</button><button type="button" data-slide-next>Berikutnya</button><button type="button" data-slide-toggle>Jeda</button></div>
                </div>
                <span class="sr-only" data-carousel-status role="status"></span>
            </section>
            @if($latest && !request()->filled('q') && !request()->filled('category'))
            <a class="hero-featured" href="{{ route('announcements.show', $latest) }}"><span class="featured-kicker"><span class="featured-icon"><x-icon name="file" /></span> TERBARU DI KANTOR</span><strong>{{ $latest->title }}</strong><span class="featured-date"><x-icon name="calendar" /><time datetime="{{ $latest->published_at->toIso8601String() }}">{{ $latest->published_at->translatedFormat('d F Y') }}</time><span>Baca pengumuman</span></span></a>
            @else
            <div class="hero-featured"><span class="featured-kicker"><span class="featured-icon"><x-icon name="file" /></span> PAPAN INFORMASI DIGITAL</span><strong>Informasi yang tepat, untuk kita semua.</strong><span class="featured-date">Temukan pengumuman melalui pencarian dan kategori.</span></div>
            @endif
        </div>
    </div>
</section>
<div class="container welcome-wrap"><div class="welcome-banner"><span class="welcome-icon"><x-icon name="wifi" /></span><div><span class="welcome-overline">SENANG ANDA ADA DI SINI</span><strong>{{ $isPortal ? 'Selamat datang di jaringan kantor' : 'Selamat datang, rekan kantor!' }}</strong><p>{{ $isPortal ? 'Silakan baca informasi dan pengumuman berikut sebelum melanjutkan aktivitas Anda.' : 'Awali aktivitas Anda dengan informasi terbaru dari kantor.' }}</p></div><span class="internal-label"><x-icon name="building" />Untuk pegawai & tamu</span></div></div>
<section class="container announcement-section" id="pengumuman">
    <div class="section-heading"><div><span class="eyebrow green">TETAP DEKAT DENGAN KABAR TERBARU</span><h2>Pengumuman kantor</h2><p>Informasi yang perlu Anda tahu, dalam satu tempat.</p></div><span class="count-label"><x-icon name="file" /><strong>{{ $total }}</strong> pengumuman aktif</span></div>
    <nav class="category-tabs" aria-label="Kategori pengumuman">
        <a class="category-tab {{ !request()->filled('category') ? 'selected' : '' }}" href="{{ route('home', ['q' => request('q')]) }}#pengumuman" @if(!request()->filled('category')) aria-current="page" @endif><x-icon name="file" />Semua informasi</a>
        @foreach($categories as $category)
        <a class="category-tab {{ request('category') === $category ? 'selected' : '' }}" href="{{ route('home', ['q' => request('q'), 'category' => $category]) }}#pengumuman" @if(request('category') === $category) aria-current="page" @endif><x-icon name="{{ $category === 'Maintenance' ? 'maintenance' : ($category === 'Kegiatan' ? 'calendar' : ($category === 'Teknologi Informasi' ? 'wifi' : ($category === 'Kepegawaian' ? 'building' : 'bell'))) }}" />{{ $category }}</a>
        @endforeach
    </nav>
    <form class="filter-bar public-filter" action="{{ route('home') }}#pengumuman" method="get">
        @if(request()->filled('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
        <div class="search-field"><x-icon name="search" /><label class="sr-only" for="q">Cari pengumuman</label><input id="q" name="q" type="search" placeholder="Cari judul atau isi pengumuman..." maxlength="100" value="{{ request('q') }}"></div>
        <button class="button button-primary" type="submit">Cari</button>
        @if(request()->filled('q') || request()->filled('category'))<a class="reset-link" href="{{ route('home') }}#pengumuman">Reset</a>@endif
    </form>
    @if($errors->any())<div class="notice notice-error" role="alert">{{ $errors->first() }}</div>@endif
    @if(request()->filled('q') || request()->filled('category'))<p class="search-summary">{{ $announcements->total() }} pengumuman ditemukan @if(request()->filled('q')) untuk “{{ request('q') }}” @endif @if(request()->filled('category')) dalam kategori {{ request('category') }} @endif.</p>@endif
    <div class="announcement-grid">
        @forelse($announcements as $announcement)
        <article class="announcement-card">
            <a class="card-image" tabindex="-1" aria-hidden="true" href="{{ route('announcements.show', $announcement) }}">
                <img src="{{ $announcement->cover_url }}" alt="" width="600" height="360" loading="lazy">
                @if($announcement->is_pinned)<span class="pinned"><x-icon name="pin" />Disematkan</span>@endif
            </a>
            <div class="card-body"><div class="card-meta"><span class="category-label">{{ $announcement->category }}</span><time datetime="{{ $announcement->published_at->toIso8601String() }}"><x-icon name="calendar" />{{ $announcement->published_at->translatedFormat('d M Y') }}</time></div><h3><a href="{{ route('announcements.show', $announcement) }}">{{ $announcement->title }}</a></h3><p>{{ \Illuminate\Support\Str::limit($announcement->body, 135) }}</p><div class="card-footer"><span class="card-author"><span class="author-mini"><x-icon name="building" /></span>Admin kantor</span><a class="read-link" href="{{ route('announcements.show', $announcement) }}">Baca selengkapnya</a></div></div>
        </article>
        @empty
        <div class="empty-state"><span class="empty-icon"><x-icon name="search" /></span><h3>{{ request()->filled('q') || request()->filled('category') ? 'Pengumuman tidak ditemukan' : 'Belum ada pengumuman' }}</h3><p>{{ request()->filled('q') || request()->filled('category') ? 'Coba kata kunci atau kategori lainnya.' : 'Informasi terbaru dari pengelola kantor akan tampil di sini.' }}</p>@if(request()->filled('q') || request()->filled('category'))<a class="button button-outline" href="{{ route('home') }}#pengumuman">Lihat semua pengumuman</a>@endif</div>
        @endforelse
    </div>
    @include('partials.pagination', ['paginator' => $announcements])
    <div class="info-note"><span class="info-note-icon"><x-icon name="shield" /></span><div><strong>Informasi dari Pusdatin Kemsos</strong><p>Seluruh pengumuman diterbitkan oleh administrator. Periksa tanggal dan informasi yang tercantum sebelum mengikuti kegiatan.</p></div></div>
</section>
@endsection