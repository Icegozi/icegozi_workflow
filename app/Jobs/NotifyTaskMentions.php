<?php

namespace App\Jobs;

use App\Models\Board;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class NotifyTaskMentions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly int $commentId,
        private readonly int $taskId,
        private readonly int $boardId,
        private readonly int $authorId,
        private readonly string $content,
        private readonly array $mentionIds,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $task = Task::find($this->taskId);
        $board = Board::find($this->boardId);
        $author = User::find($this->authorId);

        if (! $task || ! $board || ! $author) {
            return;
        }

        $members = $board->getAssignedUsersByBoardId($board->id)->keyBy('id');
        $code = Task::buildCode($board->name, $task->id);
        $url = route('tasks.edit', $code, false);
        $message = '<strong>' . e($author->name) . '</strong> đã nhắc bạn trong <strong>'
            . e($code) . '</strong> — ' . e($task->title) . '.';

        foreach (array_unique(array_filter(array_map('intval', $this->mentionIds))) as $userId) {
            $member = $members->get($userId);
            if ($userId === $author->id || ! $member || ! $this->hasMentionToken($member['name'])) {
                continue;
            }

            DB::table('notifications')->insertOrIgnore([
                'user_id' => $userId,
                'message' => $message,
                'url' => $url,
                'task_id' => $task->id,
                'dedupe_key' => "mention:{$this->commentId}",
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function hasMentionToken(string $name): bool
    {
        return preg_match(
            '/(?<!\S)@' . preg_quote($name, '/') . '(?=\s|[.,!?;:]|$)/u',
            $this->content
        ) === 1;
    }
}
