<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 'assignees' là bảng pivot (task_id + user_id) nên KHÔNG dùng xoá mềm: quan hệ belongsToMany
 * truy vấn thẳng pivot và bỏ qua scope deleted_at, khiến người phụ trách đã "gỡ" (soft delete)
 * vẫn hiện lại -> không xoá được. Bỏ cột deleted_at và dọn các dòng đã kẹt ở trạng thái soft-delete.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('assignees', 'deleted_at')) {
            return;
        }

        // Dọn dứt điểm những dòng đã bị "gỡ" trước đây nhưng còn kẹt (deleted_at != null):
        // xoá cứng để không tái xuất hiện khi cột deleted_at bị bỏ.
        DB::table('assignees')->whereNotNull('deleted_at')->delete();

        Schema::table('assignees', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        // Cố tình no-op: 'assignees' là bảng pivot, KHÔNG bao giờ dùng xoá mềm.
        // Dựng lại cột deleted_at khi rollback chỉ tái tạo đúng bug đã sửa
        // (belongsToMany bỏ qua scope deleted_at -> người đã gỡ hiện lại).
    }
};
