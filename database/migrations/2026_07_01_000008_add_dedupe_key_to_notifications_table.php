<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Khoá chống trùng: dedupeToday sinh key (date:hash(message)); mention để null.
            // NULL không tính trùng trong unique index nên mention không bị chặn.
            $table->string('dedupe_key')->nullable()->after('url');
            $table->unique(['user_id', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'dedupe_key']);
            $table->dropColumn('dedupe_key');
        });
    }
};
