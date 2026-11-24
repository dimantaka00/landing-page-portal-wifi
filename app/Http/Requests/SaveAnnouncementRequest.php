<?php

namespace App\Http\Requests;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class SaveAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        $announcement = $this->route('announcement');

        return [
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:30000'],
            'category' => ['required', Rule::in(Announcement::CATEGORIES)],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'use_default_cover' => ['sometimes', 'boolean'],
            'notify_users' => ['sometimes', 'boolean'],
            'is_pinned' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'expires_at' => ['nullable', 'date_format:Y-m-d\TH:i', function ($attribute, $value, $fail) {
                $start = $this->input('published_at') ?: now()->format('Y-m-d\TH:i');
                if ($value <= $start) {
                    $fail('Waktu berakhir harus setelah waktu publikasi.');
                }
            }],
            'delete_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:now', function ($attribute, $value, $fail) {
                if ($this->input('published_at') && $value <= $this->input('published_at')) {
                    $fail('Waktu hapus otomatis harus setelah waktu publikasi.');
                }
            }],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)->dimensions(Rule::dimensions()->maxWidth(6000)->maxHeight(6000))],
            'remove_photos' => ['nullable', 'array', 'max:5'],
            'remove_photos.*' => ['integer', 'distinct', Rule::exists('announcement_photos', 'id')->where('announcement_id', $announcement?->id ?? 0)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $existing = $this->route('announcement')?->photos()->count() ?? 0;
            $remaining = $existing - count(array_unique($this->input('remove_photos', [])));
            if ($remaining + count($this->file('photos', [])) > 5) {
                $validator->errors()->add('photos', 'Maksimal 5 foto per pengumuman. Hapus foto lama untuk menambahkan foto baru.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.', 'max' => ':attribute melebihi batas yang diperbolehkan.',
            'photos.*.image' => 'Unggahan harus berupa foto JPG, PNG, atau WebP.',
            'photos.*.mimes' => 'Format foto harus JPG, PNG, atau WebP.',
            'photos.*.max' => 'Ukuran setiap foto maksimal 5 MB.',
            'photos.*.dimensions' => 'Dimensi foto maksimal 6000 × 6000 piksel.',
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'Judul', 'body' => 'Isi pengumuman', 'category' => 'Kategori', 'status' => 'Status', 'delete_at' => 'Waktu hapus otomatis'];
    }
}
