<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->boolean('can_manage_users')->default(false);
            $table->unsignedInteger('auth_version')->default(0);
        });
        DB::table('users')->where('is_admin', true)->update(['can_manage_users' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'can_manage_users', 'auth_version']);
        });
    }
};
