<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * task_code là số tăng dần TRONG board nhưng bảng tasks không có board_id nên trước đây
 * không thể ràng buộc unique ở DB — chống trùng chỉ dựa vào lockForUpdate (có thể hụt dưới
 * READ COMMITTED). Thêm board_id + unique(board_id, task_code) làm lưới an toàn ở tầng DB.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'board_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('board_id')->nullable()->after('column_id');
            });
        }

        // Backfill board_id từ column->board. Lặp theo column để CHẠY ĐƯỢC trên mọi driver
        // (JOIN-UPDATE không di động sang sqlite dùng trong test).
        foreach (DB::table('columns')->select('id', 'board_id')->get() as $col) {
            DB::table('tasks')->where('column_id', $col->id)->update(['board_id' => $col->board_id]);
        }

        // Unique (board_id, task_code): dữ liệu cũ đã được đánh số 1..M/board (migration 000003)
        // nên không vi phạm. NULL được coi là phân biệt nên task chưa có board_id/task_code không sao.
        Schema::table('tasks', function (Blueprint $table) {
            $table->index('board_id');
            $table->unique(['board_id', 'task_code'], 'tasks_board_task_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique('tasks_board_task_code_unique');
            $table->dropIndex(['board_id']);
            $table->dropColumn('board_id');
        });
    }
};
