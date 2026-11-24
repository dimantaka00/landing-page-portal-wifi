<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveDashboardUserRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return view('admin.users.index', [
            'users' => User::query()->where('is_admin', true)->when($filters['q'] ?? null, function ($query, string $term): void {
                $query->where(fn ($query) => $query->where('name', 'like', '%'.$term.'%')->orWhere('username', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'));
            })->orderBy('name')->paginate(15)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User]);
    }

    public function edit(User $user): View
    {
        abort_unless($user->is_admin, 404);

        return view('admin.users.form', ['user' => $user]);
    }

    public function store(SaveDashboardUserRequest $request): RedirectResponse
    {
        $this->save($request, new User);

        return redirect()->route('admin.users.index')->with('success', 'Akun pengguna dashboard berhasil ditambahkan.');
    }

    public function update(SaveDashboardUserRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->is_admin, 404);
        $this->save($request, $user);

        return redirect()->route('admin.users.index')->with('success', 'Pengaturan pengguna berhasil disimpan.');
    }

    private function save(SaveDashboardUserRequest $request, User $user): void
    {
        $version = DB::transaction(function () use ($request, $user): int {
            $managers = User::where('is_admin', true)->where('is_active', true)->where('can_manage_users', true)->lockForUpdate()->get();
            abort_unless($managers->contains('id', $request->user()->id), 403);
            if ($user->exists) {
                $user->refresh();
            }
            $data = $request->validated();
            $active = $request->boolean('is_active');
            $manager = $data['role'] === 'admin';
            if ($user->exists && $user->id === $request->user()->id && (! $active || ! $manager)) {
                throw ValidationException::withMessages(['role' => 'Akun yang sedang digunakan tidak dapat dinonaktifkan atau diturunkan perannya.']);
            }
            if ($user->exists && $user->is_active && $user->can_manage_users && (! $active || ! $manager) && $managers->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Sistem harus memiliki minimal satu administrator aktif.']);
            }
            $user->forceFill([
                'name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'],
                'is_admin' => true, 'is_active' => $active, 'can_manage_users' => $manager,
            ]);
            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
            }
            $revoke = $user->exists && $user->isDirty(['password', 'username', 'email', 'is_active', 'can_manage_users']);
            if ($revoke) {
                $user->forceFill(['auth_version' => $user->auth_version + 1, 'remember_token' => Str::random(60)]);
            }
            $user->save();

            return $user->auth_version ?? 0;
        }, 3);
        if ($user->id === $request->user()->id) {
            $request->session()->put('admin_auth_version', $version);
            $request->session()->regenerate();
        }
    }
}
