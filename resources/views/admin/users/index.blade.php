@extends('layouts.admin')
@section('title', 'Pengaturan Pengguna')
@section('content')
<div class="page-heading"><div><span class="eyebrow green">PENGATURAN AKSES</span><h1>Pengaturan pengguna</h1><p class="muted">Kelola akun yang dapat masuk ke dashboard kantor.</p></div><a class="button button-primary" href="{{ route('admin.users.create') }}"><x-icon name="plus" />Tambah pengguna</a></div>
<section class="panel">
<form class="filter-bar admin-filter" action="{{ route('admin.users.index') }}" method="get"><div class="search-field"><x-icon name="search" /><label class="sr-only" for="q">Cari pengguna</label><input type="search" id="q" name="q" maxlength="100" value="{{ request('q') }}" placeholder="Cari nama, username, atau email..."></div><button class="button button-outline" type="submit">Cari</button>@if(request()->filled('q'))<a class="reset-link" href="{{ route('admin.users.index') }}">Reset</a>@endif</form>
<div class="table-wrap"><table class="announcement-table"><thead><tr><th>Pengguna</th><th>Username / email</th><th>Peran</th><th>Status</th><th>Dibuat pada (WIB)</th><th>Aksi</th></tr></thead><tbody>
@forelse($users as $account)
<tr><td><strong>{{ $account->name }}</strong>@if($account->id === auth()->id())<small>Akun Anda</small>@endif</td><td>{{ $account->username ?: 'Login melalui email' }}<small>{{ $account->email }}</small></td><td>{{ $account->can_manage_users ? 'Administrator' : 'Pengelola pengumuman' }}</td><td><span class="badge {{ $account->is_active ? 'badge-green' : 'badge-neutral' }}">{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td><time>{{ $account->created_at->translatedFormat('d M Y') }}</time><small>{{ $account->created_at->format('H:i') }} WIB</small></td><td><a class="button button-outline" href="{{ route('admin.users.edit', $account) }}" aria-label="Edit pengguna {{ $account->name }}">Edit</a></td></tr>
@empty
<tr><td colspan="6"><div class="empty-state"><x-icon name="users" /><h3>Pengguna tidak ditemukan</h3><p>Coba nama, username, atau email lain.</p></div></td></tr>
@endforelse
</tbody></table></div>
@include('partials.pagination', ['paginator' => $users])
</section>
@endsection
