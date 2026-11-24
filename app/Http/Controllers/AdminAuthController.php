<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $field = $request->has('username') ? 'username' : 'email';
        $input = $request->validate([
            $field => ['required', 'string', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
        ]);
        $identifier = Str::lower(trim($input[$field]));
        $credentialField = str_contains($identifier, '@') ? 'email' : 'username';
        $key = 'portal-login:'.hash('sha256', $identifier.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([$field => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }
        if (! Auth::attempt([$credentialField => $identifier, 'password' => $input['password'], 'is_admin' => true, 'is_active' => true])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([$field => 'Username atau kata sandi tidak sesuai.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('admin_auth_version', $request->user()->auth_version);

        return redirect()->intended(route('admin.announcements.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
