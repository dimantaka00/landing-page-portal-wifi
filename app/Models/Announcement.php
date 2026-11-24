<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasFactory;

    public const CATEGORIES = ['Umum', 'Kepegawaian', 'Kegiatan', 'Teknologi Informasi', 'Maintenance'];

    protected $fillable = ['title', 'body', 'category', 'status', 'is_pinned', 'published_at', 'expires_at', 'delete_at', 'notify_users', 'use_default_cover'];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean', 'published_at' => 'datetime', 'expires_at' => 'datetime', 'delete_at' => 'datetime', 'notify_users' => 'boolean', 'use_default_cover' => 'boolean'];
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AnnouncementPhoto::class)->orderBy('id');
    }

    public function getCategoryCoverUrlAttribute(): string
    {
        $slug = match ($this->category) {
            'Kepegawaian' => 'kepegawaian',
            'Kegiatan' => 'kegiatan',
            'Teknologi Informasi' => 'teknologi-informasi',
            'Maintenance' => 'maintenance',
            default => 'umum',
        };

        return asset('assets/category-covers/'.$slug.'.svg').'?v='.filemtime(public_path('assets/category-covers/'.$slug.'.svg'));
    }

    public function getUsesCategoryCoverAttribute(): bool
    {
        return $this->use_default_cover || $this->photos->isEmpty();
    }

    public function getCoverUrlAttribute(): string
    {
        return $this->uses_category_cover ? $this->category_cover_url : route('photos.show', $this->photos->first());
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published')->where('published_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn (Builder $q) => $q->whereNull('delete_at')->orWhere('delete_at', '>', now()));
    }

    public function getPublicationLabelAttribute(): string
    {
        if ($this->status === 'draft') {
            return 'Draf';
        }
        if ($this->delete_at && $this->delete_at->lessThanOrEqualTo(now())) {
            return 'Menunggu penghapusan';
        }
        if ($this->expires_at?->isPast()) {
            return 'Berakhir';
        }
        if ($this->published_at?->isFuture()) {
            return 'Terjadwal';
        }

        return 'Terbit';
    }
}
