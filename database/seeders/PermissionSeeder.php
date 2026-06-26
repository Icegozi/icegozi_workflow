<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            ['name' => 'board_viewer', 'description' => 'Quyền xem nội dung'],
            ['name' => 'board_editor', 'description' => 'Quyền chỉnh sửa nội dung'],
            ['name' => 'board_member_manager', 'description' => 'Quyền mời và quản lý thành viên trong bảng'],
        ];

        // Idempotent: chạy lại không tạo bản ghi trùng.
        foreach ($permissions as $permission) {
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
}
