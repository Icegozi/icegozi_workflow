<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'message',
        'is_read',
        'user_id',
        'task_id',
        'url',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Tạo thông báo cho user. Nếu $dedupeToday = true sẽ bỏ qua khi đã có
     * thông báo trùng nội dung cho user trong hôm nay (tránh spam khi cron chạy lại).
     */
    public static function notifyUser(
        int $userId,
        string $message,
        ?string $url = null,
        ?int $taskId = null,
        bool $dedupeToday = false
    ): ?self {
        if ($dedupeToday) {
            $exists = static::where('user_id', $userId)
                ->where('message', $message)
                ->whereDate('created_at', now()->toDateString())
                ->exists();
            if ($exists) {
                return null;
            }
        }

        return static::create([
            'user_id' => $userId,
            'message' => $message,
            'url' => $url,
            'task_id' => $taskId,
            'is_read' => false,
        ]);
    }
}
