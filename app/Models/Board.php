<?php

namespace App\Models;

use App\Models\Concerns\BoardRelationships;
use App\Models\Concerns\CascadesSoftDeletes;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Board extends Model
{
    use BoardRelationships;
    use CascadesSoftDeletes;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'user_id'];

    protected static function booted(): void
    {
        // Xoá mềm board -> xoá mềm luôn column (kéo theo task và các con của task) để board
        // "đã xoá" không để lại dữ liệu vẫn hiện trong truy vấn xuyên bảng (vd. "Task của tôi").
        // Xoá cứng (forceDelete) thì để FK ON DELETE CASCADE của DB lo.
        static::deleting(function (Board $board) {
            if ($board->isForceDeleting()) {
                return;
            }
            $board->columns()->get()->each->delete();
        });
    }

    /**
     * Sinh board_code (số tăng dần toàn hệ thống, unique) một cách atomic: đọc max + INSERT
     * nằm trong CÙNG transaction với lockForUpdate để hai board tạo song song không nhận
     * trùng mã (nếu không sẽ vi phạm unique và trả 500).
     */
    public function save(array $options = []): bool
    {
        if ($this->exists || ! empty($this->board_code)) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($options) {
            $this->board_code = (static::withTrashed()->lockForUpdate()->max('board_code') ?? 0) + 1;

            return parent::save($options);
        });
    }

    // Lấy tất cả board theo user_id
    public static function getBoardsByUser(int $userId)
    {
        return self::where('user_id', $userId)->get();
    }

    // Thêm board mới
    public static function createBoard(array $data)
    {
        return self::create($data);
    }

    // Cập nhật board theo ID
    public static function updateBoard(int $id, array $data)
    {
        $board = self::findOrFail($id);
        $board->update($data);

        return $board;
    }

    // Xoá board theo ID
    public static function deleteBoard(int $id)
    {
        $board = self::findOrFail($id);

        return $board->delete();
    }

    // Tìm kiếm board theo khoảng thời gian (created_at)
    public static function searchByDate(string $from, string $to)
    {
        return self::whereBetween('created_at', [
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay(),
        ])->get();
    }

    // lấy column từ id
    public static function getBoardData(int $id)
    {
        return self::with(['user', 'columns.tasks'])->findOrFail($id);
    }

    public function getMembersWithRoles()
    {
        $members = [];
        $permissionUserPivots = $this->boardPermissionUsers()->with('user', 'permission')->get();

        foreach ($permissionUserPivots as $pivot) {
            if ($pivot->user && $pivot->user->id !== $this->user_id) {
                if (! isset($members[$pivot->user->id])) {
                    $members[$pivot->user->id] = [
                        'user' => $pivot->user,
                        'roles' => [],
                    ];
                }
                if ($pivot->permission) {
                    $members[$pivot->user->id]['roles'][] = $pivot->permission->name;
                }
            }
        }

        return array_values($members);
    }

    // public function assignedUsers()
    // {
    //     return User::whereIn('id', function ($query) {
    //         $query->select('permission_users.user_id')
    //             ->from('permission_users')
    //             ->join('board_permissions', 'permission_users.id', '=', 'board_permissions.permission_user_id')
    //             ->where('board_permissions.board_id', $this->id);
    //     })->get();
    // }

    public function assignedUsers(Board $board)
    {
        $permissionUserIds = DB::table('permission_users')
            ->join('board_permissions', 'permission_users.id', '=', 'board_permissions.permission_user_id')
            ->where('board_permissions.board_id', $board->id)
            ->pluck('permission_users.user_id')
            ->toArray();
        $permissionUserIds[] = $board->user_id;
        $userIds = array_unique($permissionUserIds);

        return User::whereIn('id', $userIds)->get();
    }

    public function getAssignedUsersByBoardId($boardId)
    {
        $permissionUserIds = DB::table('permission_users')
            ->join('board_permissions', 'permission_users.id', '=', 'board_permissions.permission_user_id')
            ->where('board_permissions.board_id', $boardId)
            ->pluck('permission_users.user_id')
            ->toArray();

        $boardOwnerId = DB::table('boards')->where('id', $boardId)->value('user_id');

        $userIds = array_unique(array_merge($permissionUserIds, [$boardOwnerId]));

        return User::whereIn('id', $userIds)
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
            ]);
    }

    public function getBoardNameById($board_id)
    {
        return self::where('id', $board_id)->value('name');
    }
}
