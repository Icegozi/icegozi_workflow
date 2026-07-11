<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'task_code')) {
            Schema::table('tasks', function (Blueprint $table) {
                // Mã task hiển thị: số tự nhiên tăng dần TRONG TỪNG board (task belongs to column belongs to board).
                $table->unsignedBigInteger('task_code')->nullable()->after('id');
            });
        }

        // Backfill: với mỗi board, đánh số task 1..M theo thứ tự id.
        foreach (DB::table('boards')->orderBy('id')->pluck('id') as $boardId) {
            $columnIds = DB::table('columns')->where('board_id', $boardId)->pluck('id');
            if ($columnIds->isEmpty()) {
                continue;
            }

            $seq = 0;
            $taskIds = DB::table('tasks')->whereIn('column_id', $columnIds)->orderBy('id')->pluck('id');
            foreach ($taskIds as $taskId) {
                DB::table('tasks')->where('id', $taskId)->update(['task_code' => ++$seq]);
            }
        }

        // Index để tra cứu nhanh theo mã (không unique toàn cục vì mã lặp lại giữa các board).
        Schema::table('tasks', function (Blueprint $table) {
            $table->index('task_code');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['task_code']);
            $table->dropColumn('task_code');
        });
    }
};
