<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAnnouncementRequest;
use App\Models\Announcement;
use App\Services\AnnouncementDeletion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminAnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:draft,published']]);

        return view('admin.index', [
            'announcements' => Announcement::with('photos')->when($filters['q'] ?? null, fn ($q, $term) => $q->where('title', 'like', '%'.$term.'%'))
                ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->latest('updated_at')->paginate(12)->withQueryString(),
            'total' => Announcement::count(), 'published' => Announcement::published()->count(),
            'drafts' => Announcement::where('status', 'draft')->count(),
            'scheduled' => Announcement::where('status', 'published')->where('published_at', '>', now())->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.form', ['announcement' => new Announcement(['category' => 'Umum', 'status' => 'draft']), 'categories' => Announcement::CATEGORIES]);
    }

    public function store(SaveAnnouncementRequest $request): RedirectResponse
    {
        $this->save($request, new Announcement);

        return redirect()->route('admin.announcements.index')->with('success', 'Pengumuman berhasil ditambahkan.');
    }

    public function edit(Announcement $announcement): View
    {
        return view('admin.form', ['announcement' => $announcement->load('photos'), 'categories' => Announcement::CATEGORIES]);
    }

    public function update(SaveAnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $this->save($request, $announcement);

        return redirect()->route('admin.announcements.index')->with('success', 'Perubahan pengumuman berhasil disimpan.');
    }

    private function save(SaveAnnouncementRequest $request, Announcement $announcement): void
    {
        $data = Arr::except($request->validated(), ['photos', 'remove_photos']);
        $data['notify_users'] = $request->boolean('notify_users', true);
        $data['is_pinned'] = $request->boolean('is_pinned');
        $data['published_at'] = $data['published_at'] ?? ($data['status'] === 'published' ? now() : null);
        $uploaded = [];
        $removed = [];
        try {
            DB::transaction(function () use ($request, $announcement, $data, &$uploaded, &$removed) {
                $announcement->fill($data)->save();
                if ($announcement->status === 'published' && $announcement->notify_users) {
                    $announcement->increment('notification_revision');
                }
                foreach ($announcement->photos()->whereIn('id', $request->input('remove_photos', []))->get() as $photo) {
                    $removed[] = $photo->path;
                    $photo->delete();
                }
                foreach ($request->file('photos', []) as $file) {
                    $path = $file->store('announcements', 'local');
                    if ($path === false) {
                        throw new \RuntimeException('Foto tidak dapat disimpan.');
                    }
                    $uploaded[] = $path;
                    $announcement->photos()->create(['path' => $path]);
                }
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($uploaded);
            throw $error;
        }
        Storage::disk('local')->delete($removed);
    }

    public function destroy(Request $request, Announcement $announcement, AnnouncementDeletion $deletion): RedirectResponse
    {
        $deletion->delete($announcement, actor: $request->user());

        return redirect()->route('admin.announcements.index')->with('success', 'Pengumuman berhasil dihapus.');
    }
}
