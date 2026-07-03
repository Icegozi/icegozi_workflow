<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Các bảng nghiệp vụ áp dụng xóa mềm (deleted_at). Bỏ qua các bảng hạ tầng của
     * framework (jobs, tokens, reset mật khẩu...) vì không dùng xóa mềm.
     */
    private array $tables = [
        'boards', 'columns', 'tasks', 'comments', 'attachments', 'checklists',
        'task_histories', 'labels', 'statuses', 'permissions',
        'board_invitations', 'notifications',
        'assignees', 'board_templates', 'chart_settings',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (Schema::hasTable($name) && ! Schema::hasColumn($name, 'deleted_at')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            if (Schema::hasTable($name) && Schema::hasColumn($name, 'deleted_at')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
