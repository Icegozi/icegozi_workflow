<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // username: định danh đăng nhập thay email. Nullable để tương thích user cũ
            // (chưa có username); đăng ký mới sẽ bắt buộc. Unique để dùng làm khoá đăng nhập.
            $table->string('username', 50)->nullable()->unique()->after('name');
            // Ảnh đại diện: đường dẫn nội bộ dạng "/storage/avatars/..." (upload) — null -> fallback pravatar.
            $table->string('avatar_url')->nullable()->after('email');
            // Mạng xã hội: {facebook, twitter, linkedin, github, website} -> URL.
            $table->json('social')->nullable()->after('avatar_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'avatar_url', 'social']);
        });
    }
};
