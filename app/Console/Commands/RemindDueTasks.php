<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RemindDueTasks extends Command
{
    protected $signature = 'tasks:remind';

    protected $description = 'Tạo thông báo cho người phụ trách khi task sắp đến hạn hoặc quá hạn';

    public function handle(): int
    {
        $today = Carbon::today();
        $created = 0;

        // Task còn hạn (không rỗng), chưa hoàn thành, có người phụ trách.
        $tasks = Task::whereNotNull('due_date')
            ->whereHas('assignees')
            ->with(['assignees', 'status', 'column.board'])
            ->get();

        foreach ($tasks as $task) {
            if ($task->status && $task->status->is_completed) {
                continue;
            }

            $due = Carbon::parse($task->due_date)->startOfDay();
            $diff = $today->diffInDays($due, false);   // <0 quá hạn, 0 hôm nay, 1 ngày mai

            if ($diff > 1) {
                continue;   // chỉ nhắc khi quá hạn / hôm nay / ngày mai
            }

            $board = $task->column?->board;
            $code = Task::buildCode($board?->name, $task->id);
            $url = route('tasks.edit', $code, false);
            $dueStr = $due->format('d/m/Y');

            $prefix = "<strong>{$code}</strong> — <strong>{$task->title}</strong>";
            if ($diff < 0) {
                $msg = "⚠️ Công việc {$prefix} đã <strong>quá hạn</strong> ({$dueStr}).";
            } elseif ($diff === 0) {
                $msg = "⏰ Công việc {$prefix} đến hạn <strong>hôm nay</strong> ({$dueStr}).";
            } else {
                $msg = "🔔 Công việc {$prefix} đến hạn <strong>ngày mai</strong> ({$dueStr}).";
            }

            foreach ($task->assignees as $user) {
                if (Notification::notifyUser($user->id, $msg, $url, $task->id, true)) {
                    $created++;
                }
            }
        }

        $this->info("Đã tạo {$created} thông báo nhắc hạn.");

        return self::SUCCESS;
    }
}
