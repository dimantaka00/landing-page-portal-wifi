@extends('layouts.admin')
@section('title', 'Log Penghapusan')
@section('content')
<div class="page-heading"><div><span class="eyebrow green">RIWAYAT SISTEM</span><h1>Log penghapusan</h1><p class="muted">Catatan pengumuman yang dihapus otomatis maupun oleh admin. Seluruh waktu dalam WIB.</p></div><a class="button button-outline" href="{{ route('admin.announcements.index') }}">Daftar pengumuman</a></div>
<section class="panel">
    <form action="{{ route('admin.deletion-logs.index') }}" method="get" class="filter-bar admin-filter">
        <div class="search-field"><x-icon name="search" /><label class="sr-only" for="q">Cari judul dalam log</label><input type="search" id="q" name="q" maxlength="100" value="{{ request('q') }}" placeholder="Cari judul pengumuman..."></div>
        <label class="sr-only" for="reason">Jenis penghapusan</label><select id="reason" name="reason"><option value="">Semua penghapusan</option value="automatic" @selected(request('reason') === 'automatic')>Otomatis</option><option value="manual" @selected(request('reason') === 'manual')>Manual</option></select>
        <button type="submit" class="button button-outline">Cari</button>
        @if(request()->filled('q') || request()->filled('reason'))<a class="reset-link" href="{{ route('admin.deletion-logs.index') }}">Reset</a>@endif
    </form>
    <div class="table-wrap"><table class="announcement-table"><thead><tr><th>Pengumuman</th><th>Kategori</th><th>Penghapusan</th><th>Jadwal hapus (WIB)</th><th>Waktu dihapus (WIB)</th><th>Foto</th></tr></thead><tbody>
    @forelse($logs as $entry)
        <tr><td><strong>{{ $entry->title }}</strong><small>ID #{{ $entry->announcement_id }}</small></td><td>{{ $entry->category }}</td><td><span class="badge {{ $entry->reason === 'automatic' ? 'badge-blue' : 'badge-neutral' }}">{{ $entry->reason === 'automatic' ? 'Otomatis' : 'Manual' }}</span><small>{{ $entry->actor }}</small></td><td>@if($entry->scheduled_at)<time>{{ \Illuminate\Support\Carbon::parse($entry->scheduled_at)->translatedFormat('d M Y') }}</time><small>{{ \Illuminate\Support\Carbon::parse($entry->scheduled_at)->format('H:i:s') }} WIB</small>@else<span class="muted">Tidak dijadwalkan</span>@endif</td><td><time>{{ \Illuminate\Support\Carbon::parse($entry->deleted_at)->translatedFormat('d M Y') }}</time><small>{{ \Illuminate\Support\Carbon::parse($entry->deleted_at)->format('H:i:s') }} WIB</small></td><td>{{ $entry->photos_removed_at ? 'Selesai dibersihkan' : 'Menunggu pembersihan ulang' }}</td></tr>
    @empty
        <tr><td colspan="6"><div class="empty-state"><x-icon name="clock" /><h3>Belum ada catatan penghapusan</h3><p>Riwayat akan muncul setelah pengumuman dihapus.</p></div></td></tr>
    @endforelse
    </tbody></table></div>
    @include('partials.pagination', ['paginator' => $logs])
</section>
@endsection