<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class TaskHistory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'task_id',
        'user_id',
        'action',
        'note',
    ];

    /**
     * Get the task that owns the history.
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Tạo phương thức tạo lịch sử hành động
    public function createHistory($taskId, $userId, $action, $note)
    {
        return self::create([
            'task_id' => $taskId,
            'user_id' => $userId,
            'action' => $action,
            'note' => $note,
        ]);
    }

    public function logTaskHistory($task, $action, $oldColumnName = null, $newColumnName = null)
    {
        if (is_array($task)) {
            $task = Task::findOrFail($task['id']);
        }
        $userId = Auth::id();
        // Escape dữ liệu người dùng nhập vì note được render bằng v-html (chống stored XSS)
        $userName = e(Auth::user()->name);
        $title = e($task->title);
        $act = e($action);
        $oldCol = e($oldColumnName);
        $newCol = e($newColumnName);

        if ($action === 'di chuyển' && $oldColumnName && $newColumnName) {
            $note = "<strong> {$userName} </strong> đã <strong>{$act}</strong> nhiệm vụ "
                . "<strong>{$title}</strong> từ cột <strong>{$oldCol}</strong> "
                . "sang cột <strong>{$newCol}</strong>.";
        } else {
            $note = "Nhiệm vụ <strong>{$title}</strong> đã được "
                . "<strong>{$act}</strong> bởi <strong>{$userName}</strong>.";
        }

        if ($action === 'tạo' || $action === 'thêm bình luận') {
            $note = "Nhiệm vụ <strong>{$title}</strong> đã được "
                . "<strong>{$act}</strong> bởi <strong>{$userName}</strong>.";
        }

        if ($action === 'xóa bình luận') {
            $note = "Nhiệm vụ <strong>{$title}</strong> đã bị "
                . "<strong>{$act}</strong> bởi <strong>{$userName}</strong>.";
        }

        self::createHistory($task->id, $userId, $action, $note);
    }
}
