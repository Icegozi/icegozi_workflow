<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Board;
use App\Models\BoardPermission;
use App\Models\Permission;
use App\Models\PermissionUser;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Cấp một role cấp-bảng cho user qua chuỗi pivot permission_users -> board_permissions.
 * Cùng logic với BoardMembershipController::grantBoardPermission, tách ra để luồng
 * "xin quyền công việc" dùng lại mà không sao chép.
 */
trait GrantsBoardPermission
{
    protected function grantBoardPermission(Board $board, User $user, string $permissionName): bool
    {
        $permission = Permission::firstWhere('name', $permissionName);
        if (! $permission) {
            Log::error("Permission {$permissionName} not found.");

            return false;
        }

        // Liên kết user <-> permission (idempotent).
        $permissionUser = PermissionUser::firstOrCreate(
            ['user_id' => $user->id, 'permission_id' => $permission->id]
        );

        // Gắn liên kết đó vào board (idempotent).
        BoardPermission::firstOrCreate(
            ['board_id' => $board->id, 'permission_user_id' => $permissionUser->id]
        );

        return true;
    }
}
