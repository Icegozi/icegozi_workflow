<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Sửa lỗi gõ sai cột `massage` -> `message` cho các DB đã migrate trước đó.
     * Dùng raw SQL để không phụ thuộc doctrine/dbal, và phòng thủ theo cột đang tồn tại.
     */
    public function up(): void
    {
        if (Schema::hasColumn('notifications', 'massage') && ! Schema::hasColumn('notifications', 'message')) {
            DB::statement('ALTER TABLE `notifications` CHANGE `massage` `message` TEXT NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notifications', 'message') && ! Schema::hasColumn('notifications', 'massage')) {
            DB::statement('ALTER TABLE `notifications` CHANGE `message` `massage` TEXT NOT NULL');
        }
    }
};
