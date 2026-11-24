<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(7),
            'body' => fake()->paragraphs(3, true),
            'category' => 'Umum',
            'status' => 'published',
            'is_pinned' => false,
            'published_at' => now()->subMinute(),
            'expires_at' => null,
        ];
    }
}
