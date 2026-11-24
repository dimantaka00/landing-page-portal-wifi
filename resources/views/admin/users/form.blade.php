@extends('layouts.admin')
@section('title', $user->exists ? 'Edit Pengguna' : 'Tambah Pengguna')
@section('content')
<div class="page-heading"><div><a class="back-link" href="{{ route('admin.users.index') }}">Daftar pengguna</a><h1>{{ $user->exists ? 'Edit pengguna' : 'Tambah pengguna' }}</h1><p class="muted">Atur identitas dan akses dashboard pengguna.</p></div></div>
<form class="user-settings-form panel form-panel" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" method="post">
@csrf
@if($user->exists)
    @method('PUT')
@endif
<div class="field"><label for="name">Nama lengkap <span class="required">*</span></label><input id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="100" required autocomplete="name"></div>
<div class="field"><label for="username">Username {{ $user->exists ? '(opsional)' : '*' }}</label><input id="username" name="username" value="{{ old('username', $user->username) }}" minlength="3" maxlength="32" @required(!$user->exists) autocomplete="username" aria-describedby="username-help"><small id="username-help">Diawali huruf; gunakan 3–32 karakter: huruf kecil, angka, titik, garis bawah, atau tanda hubung. Pengguna juga dapat login melalui email.</small></div>
<div class="field"><label for="email">Email <span class="required">*</span></label><input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="254" required autocomplete="email"></div>
<div class="field"><label for="role">Peran <span class="required">*</span></label><select id="role" name="role" required><option value="editor" @selected(old('role', $user->can_manage_users ? 'admin' : 'editor') === 'editor')>Pengelola pengumuman</option><option value="admin" @selected(old('role', $user->can_manage_users ? 'admin' : 'editor') === 'admin')>Administrator</option></select><small>Administrator dapat mengelola akun pengguna. Pengelola pengumuman dapat mengelola pengumuman dan melihat log penghapusan.</small></div>
<input type="hidden" name="is_active" value="0">
<label class="checkbox-label"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->exists ? $user->is_active : true))><span><strong>Akun aktif</strong><small>Akun nonaktif tidak dapat login. Akun Anda sendiri harus tetap aktif sebagai administrator.</small></span></label>
<div class="field"><label for="new_password">{{ $user->exists ? 'Password baru (opsional)' : 'Password *' }}</label><input id="new_password" name="password" type="password" minlength="12" maxlength="128" autocomplete="new-password" @required(!$user->exists)><small>Minimal 12 karakter. {{ $user->exists ? 'Kosongkan agar password tetap sama.' : 'Sampaikan password kepada pengguna secara pribadi.' }}</small></div>
<div class="field"><label for="password_confirmation">Konfirmasi password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="12" maxlength="128" autocomplete="new-password" @required(!$user->exists)></div>
<div class="user-settings-actions"><button class="button button-primary" type="submit">Simpan pengguna</button><a class="button button-outline" href="{{ route('admin.users.index') }}">Batal</a></div>
</form>
@endsection
