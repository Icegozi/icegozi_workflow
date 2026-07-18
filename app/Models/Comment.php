<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['content', 'user_id', 'task_id'];

    protected $casts = ['created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected $appends = ['time_ago', 'user_avatar_url', 'user_display_name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function getTimeAgoAttribute()
    {
        return $this->created_at ? $this->created_at->diffForHumans() : 'Không xác định';
    }

    public function getUserAvatarUrlAttribute()
    {
        if ($this->user) {
            return $this->user->avatar_url;
        }

        return '/images/default-avatar.svg';
    }

    public function getUserDisplayNameAttribute()
    {
        return $this->user ? $this->user->name : 'Người dùng ẩn danh';
    }
}
