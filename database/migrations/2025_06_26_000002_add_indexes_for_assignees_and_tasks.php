<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * - Chặn gán trùng (cùng task + cùng user) ở mức DB.
     * - Thêm chỉ mục (column_id, position) phục vụ render bảng theo thứ tự.
     */
    public function up(): void
    {
        // Dọn dữ liệu trùng tồn đọng trước khi tạo unique index (nếu có).
        // Delete duplicate rows in a database-agnostic way so the migration
        // also works with the SQLite in-memory test database.
        $duplicateIds = DB::table('assignees as duplicate')
            ->join('assignees as original', function ($join) {
                $join->on('duplicate.task_id', '=', 'original.task_id')
                    ->on('duplicate.user_id', '=', 'original.user_id')
                    ->whereColumn('duplicate.id', '>', 'original.id');
            })
            ->pluck('duplicate.id');

        $duplicateIds->chunk(1000)->each(function ($ids) {
            DB::table('assignees')->whereIn('id', $ids)->delete();
        });

        Schema::table('assignees', function (Blueprint $table) {
            $table->unique(['task_id', 'user_id'], 'assignees_task_user_unique');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['column_id', 'position'], 'tasks_column_position_index');
        });
    }

    public function down(): void
    {
        Schema::table('assignees', function (Blueprint $table) {
            $table->dropUnique('assignees_task_user_unique');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_column_position_index');
        });
    }
};
