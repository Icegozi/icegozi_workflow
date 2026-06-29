<?php

namespace App\Models\Concerns;

use App\Models\Attachment;
use App\Models\Checklist;
use App\Models\Column;
use App\Models\Comment;
use App\Models\TaskHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait TaskRelationships
{
    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function taskHistories(): HasMany
    {
        return $this->hasMany(TaskHistory::class)->latest();
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'assignees', 'task_id', 'user_id')->withTimestamps();
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class)->orderBy('position');
    }

    public function board()
    {
        // Null-safe: task có thể tạm thời không có column hợp lệ.
        return optional($this->column)->board;
    }
}
