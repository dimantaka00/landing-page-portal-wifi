<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveDashboardUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_active && $this->user()?->is_admin && $this->user()?->can_manage_users);
    }

    protected function prepareForValidation(): void
    {
        $username = $this->input('username');
        $email = $this->input('email');
        $this->merge([
            'username' => is_string($username) ? (trim($username) === '' ? null : Str::lower(trim($username))) : $username,
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
        ]);
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => [$user ? 'nullable' : 'required', 'string', 'min:3', 'max:32', 'regex:/^[a-z][a-z0-9._-]*$/', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['required', 'email', 'max:254', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in(['admin', 'editor'])],
            'is_active' => ['required', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:12', 'max:128', 'confirmed'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'Nama lengkap', 'username' => 'Username', 'email' => 'Email', 'role' => 'Peran', 'password' => 'Password', 'is_active' => 'Status akun'];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'unique' => ':attribute sudah digunakan.',
            'password.min' => 'Password minimal 12 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'username.regex' => 'Username harus diawali huruf dan hanya berisi huruf kecil, angka, titik, garis bawah, atau tanda hubung.',
        ];
    }
}
