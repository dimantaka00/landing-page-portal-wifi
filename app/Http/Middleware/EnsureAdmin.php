<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->refresh();
        abort_unless($user?->is_admin, 403);
        if (! $user->is_active || (int) $request->session()->get('admin_auth_version', 0) !== $user->auth_version) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => 'Akses akun berubah. Silakan login kembali atau hubungi administrator.']);
        }

        return $next($request);
    }
}
