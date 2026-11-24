<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->timestamp('delete_at')->nullable()->index();
        });
        Schema::create('announcement_deletion_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('announcement_id')->unique();
            $table->string('title', 180);
            $table->string('category', 40);
            $table->string('reason', 20);
            $table->string('actor', 100);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('deleted_at')->index();
            $table->json('photo_paths');
            $table->timestamp('photos_removed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_deletion_logs');
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex(['delete_at']);
            $table->dropColumn('delete_at');
        });
    }
};
