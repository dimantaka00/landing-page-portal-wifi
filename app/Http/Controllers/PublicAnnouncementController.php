<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementPhoto;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicAnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(Announcement::CATEGORIES)],
        ]);
        $announcements = Announcement::published()->with('photos')
            ->when($filters['q'] ?? null, function (Builder $query, string $term) {
                $query->where(fn (Builder $q) => $q->where('title', 'like', "%{$term}%")->orWhere('body', 'like', "%{$term}%"));
            })
            ->when($filters['category'] ?? null, fn (Builder $q, string $category) => $q->where('category', $category))
            ->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id')->paginate(9)->withQueryString();

        return view('public.index', [
            'announcements' => $announcements,
            'categories' => Announcement::CATEGORIES,
            'latest' => Announcement::published()->orderByDesc('published_at')->first(),
            'total' => Announcement::published()->count(),
            'isPortal' => $request->routeIs('portal'),
        ]);
    }

    public function show(Announcement $announcement): View
    {
        abort_unless(Announcement::published()->whereKey($announcement->id)->exists(), 404);

        return view('public.show', ['announcement' => $announcement->load('photos')]);
    }

    public function photo(Request $request, AnnouncementPhoto $photo): StreamedResponse
    {
        $visible = Announcement::published()->whereKey($photo->announcement_id)->exists();
        abort_unless($visible || $request->user()?->is_admin, 404);
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
