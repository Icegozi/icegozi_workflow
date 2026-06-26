<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
    ];

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(Assignee::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function permisstionUsers(): HasMany
    {
        return $this->hasMany(PermissionUser::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function taskHistorys(): HasMany
    {
        return $this->hasMany(TaskHistory::class);
    }

    public function boards()
    {
        return $this->hasMany(Board::class);
    }

    public function boardsOwned(): HasMany
    {
        return $this->hasMany(Board::class, 'user_id');
    }

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

    public static function register(array $data)
    {
        // is_admin/status không nằm trong $fillable nên dùng forceFill để gán có chủ đích.
        $user = new self();
        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => false,
            'status' => 'active',
        ])->save();

        return $user;
    }

    public static function login(array $credentials, bool $remember = false): bool
    {
        $user = self::where('email', $credentials['email'])->first();

        if (! $user || $user->status !== 'active') {
            return false;
        }

        if (Auth::attempt($credentials, $remember)) {
            session()->regenerate();

            return true;
        }

        return false;
    }

    public static function logout()
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }

    public function hasBoardPermission(Board $board, string $permissionName): bool
    {
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
        $boardIdsWithPermissions = BoardPermission::join('permission_users', 'board_permissions.permission_user_id', '=', 'permission_users.id')
            ->where('permission_users.user_id', $this->id)
            ->distinct()
            ->pluck('board_permissions.board_id');

        $memberOf = Board::whereIn('id', $boardIdsWithPermissions)->get();

        return $owned->merge($memberOf)
            ->unique('id')
            ->sortByDesc('created_at')
            ->unique('id');
    }

    public static function addUser(array $data): ?User
    {
        // Chỉ gọi từ luồng quản trị; gán is_admin/status có chủ đích qua forceFill.
        $user = new self();
        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => $data['is_admin'] ?? false,
            'status' => $data['status'] ?? 'active',
        ])->save();

        return $user;
    }

    public static function updateUserById(int $id, array $data): bool
    {
        $user = self::find($id);
        if (! $user) {
            return false;
        }

        // Chỉ chấp nhận các trường được phép; is_admin/status gán có chủ đích (luồng quản trị).
        $fields = [];
        foreach (['name', 'email', 'is_admin', 'status'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }
        if (! empty($data['password'])) {
            $fields['password'] = Hash::make($data['password']);
        }

        return $user->forceFill($fields)->save();
    }

    public static function deleteUserById(int $id): bool
    {
        if (auth()->id() === $id) {
            return false;
        }
        $user = self::find($id);
        if (! $user) {
            return false;
        }

        return $user->delete();
    }

    public static function searchUsers(string $keyword)
    {
        return self::where(function ($query) use ($keyword) {
            $query->where('name', 'like', "%{$keyword}%")
                ->orWhere('email', 'like', "%{$keyword}%");
        })->get();
    }
}
