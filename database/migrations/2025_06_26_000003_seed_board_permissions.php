<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Các quyền cấp bảng là DỮ LIỆU THAM CHIẾU BẮT BUỘC (validation dùng exists:permissions,name
     * khi mời thành viên). Seed qua migration để mọi môi trường đều có sau khi `migrate`,
     * không phụ thuộc cờ RUN_SEEDERS. Idempotent: chỉ chèn khi chưa tồn tại.
     */
    private array $permissions = [
        ['name' => 'board_viewer', 'description' => 'Quyền xem nội dung'],
        ['name' => 'board_editor', 'description' => 'Quyền chỉnh sửa nội dung'],
        ['name' => 'board_member_manager', 'description' => 'Quyền mời và quản lý thành viên trong bảng'],
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                [
                    'description' => $permission['description'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', array_column($this->permissions, 'name'))
            ->delete();
    }
};
