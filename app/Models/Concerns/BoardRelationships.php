<?php

namespace App\Models\Concerns;

use App\Models\BoardInvitation;
use App\Models\BoardPermission;
use App\Models\Column;
use App\Models\Label;
use App\Models\PermissionUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

trait BoardRelationships
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function columns(): HasMany
    {
        // Mặc định sắp xếp các cột theo vị trí 'position'
        return $this->hasMany(Column::class)->orderBy('position', 'asc');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(BoardInvitation::class);
    }

    public function boardPermissionUsers(): HasManyThrough
    {
        // Board -> board_permissions -> permission_users
        return $this->hasManyThrough(
            PermissionUser::class,
            BoardPermission::class,
            'board_id',
            'id',
            'id',
            'permission_user_id'
        );
    }
}
