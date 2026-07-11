<?php

namespace App\Models\Concerns;

use App\Models\Board;
use App\Models\BoardPermission;
use App\Models\Permission;
use App\Models\PermissionUser;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait UserBoardPermissions
{
    /**
     * Permissions explicitly granted to this user (global or context-specific entries).
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_users');
    }

    /**
     * Get the PermissionUser pivot entries for this user.
     */
    public function permissionUserPivots(): HasMany
    {
        return $this->hasMany(PermissionUser::class); // Assuming PermissionUser model exists
    }

    public function hasBoardPermission(Board $board, string $permissionName): bool
    {
        // Board đã xoá mềm -> không ai còn quyền (kể cả owner). Cần chặn tại đây vì các
        // truy vấn RBAC dùng DB::table()/join thô KHÔNG áp global scope của SoftDeletes,
        // nên pivot board_permissions còn sót vẫn có thể cấp quyền cho board đã xoá.
        if ($board->trashed()) {
            return false;
        }

        if ($board->user_id === $this->id) {
            return true;
        }

        // Gộp 3 truy vấn thành 1 join để tránh N+1 khi kiểm tra quyền hàng loạt.
        return BoardPermission::query()
            ->where('board_permissions.board_id', $board->id)
            ->join('permission_users', 'board_permissions.permission_user_id', '=', 'permission_users.id')
            ->join('permissions', 'permission_users.permission_id', '=', 'permissions.id')
            ->where('permission_users.user_id', $this->id)
            ->where('permissions.name', $permissionName)
            ->exists();
    }

    public function getRoleForBoard(Board $board): ?string
    {
        if ($board->user_id === $this->id) {
            return 'owner';
        }

        $permissionNames = ['board_member_manager', 'board_editor', 'board_viewer'];
        foreach ($permissionNames as $pName) {
            if ($this->hasBoardPermission($board, $pName)) {
                return $pName;
            }
        }

        return null;
    }

    public function getAllAccessibleBoards()
    {
        $owned = $this->boardsOwned()->get();
        $boardIdsWithPermissions = BoardPermission::join(
            'permission_users',
            'board_permissions.permission_user_id',
            '=',
            'permission_users.id'
        )
            ->where('permission_users.user_id', $this->id)
            ->distinct()
            ->pluck('board_permissions.board_id');

        $memberOf = Board::whereIn('id', $boardIdsWithPermissions)->get();

        return $owned->merge($memberOf)
            ->unique('id')
            ->sortByDesc('created_at')
            ->unique('id');
    }
}
