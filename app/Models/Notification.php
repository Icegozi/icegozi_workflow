<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'message',
        'is_read',
        'user_id',
        'task_id',
        'url',
        'dedupe_key',
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
        $attributes = [
            'user_id' => $userId,
            'message' => $message,
            'url' => $url,
            'task_id' => $taskId,
            'is_read' => false,
        ];

        if (! $dedupeToday) {
            return static::create($attributes);
        }

        // Chống trùng nguyên tử: dựa vào unique(user_id, dedupe_key).
        // Key = ngày:hash(message) -> mỗi (user, nội dung) chỉ 1 thông báo/ngày.
        // Race song song: bản insert thứ 2 vi phạm unique -> nuốt lỗi, trả null.
        $attributes['dedupe_key'] = now()->toDateString() . ':' . md5($message);
        try {
            return static::create($attributes);
        } catch (QueryException $e) {
            return null;
        }
    }
}
