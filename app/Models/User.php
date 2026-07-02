<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\UserAuthentication;
use App\Models\Concerns\UserBoardPermissions;
use App\Models\Concerns\UserManagement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use UserAuthentication;
    use UserBoardPermissions;
    use UserManagement;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'avatar_url',
        'social',
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
        'social' => 'array',
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
}
