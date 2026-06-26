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
        DB::statement('
            DELETE a1 FROM assignees a1
            INNER JOIN assignees a2
                ON a1.task_id = a2.task_id
               AND a1.user_id = a2.user_id
               AND a1.id > a2.id
        ');

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
