<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('notify_users')->default(true);
            $table->unsignedInteger('notification_revision')->default(0);
            $table->unsignedInteger('dispatched_revision')->default(0);
        });
        Schema::create('portal_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('revision');
            $table->timestamp('created_at');
            $table->unique(['announcement_id', 'revision']);
        });
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint_hash', 64)->unique();
            $table->string('token_hash', 64);
            $table->text('details');
            $table->unsignedBigInteger('last_notification_id')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('portal_notifications');
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['notify_users', 'notification_revision', 'dispatched_revision']);
        });
    }
};
