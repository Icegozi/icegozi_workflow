<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'key',
        'name',
        'color',
        'position',
        'is_default',
        'is_completed',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_completed' => 'boolean',
        'position' => 'integer',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** Trạng thái mặc định khi tạo task mới. */
    public static function default(): ?self
    {
        return static::where('is_default', true)->orderBy('position')->first()
            ?? static::orderBy('position')->first();
    }
}
