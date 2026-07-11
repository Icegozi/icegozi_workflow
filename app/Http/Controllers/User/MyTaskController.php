<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Auth;
use Carbon\Carbon;
use Inertia\Inertia;

class MyTaskController extends Controller
{
    /** Gom mọi task được giao cho user hiện tại trên tất cả board, kèm nhóm theo hạn. */
    public function index()
    {
        $user = Auth::user();

        $tasks = Task::whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
            ->with(['column.board', 'status', 'labels'])
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->get();

        $today = Carbon::today();

        // Cache role theo board: nhiều task cùng board -> chỉ hỏi quyền 1 lần (tránh N+1).
        $roleCache = [];

        $data = $tasks->map(function (Task $t) use ($today, $user, &$roleCache) {
            $board = $t->column?->board;
            $due = $t->due_date ? Carbon::parse($t->due_date)->startOfDay() : null;

            // Quyền thực tế của user trên board của task (để modal ẩn/hiện nút Chỉnh sửa).
            $role = null;
            if ($board) {
                $roleCache[$board->id] ??= $user->getRoleForBoard($board);
                $role = $roleCache[$board->id];
            }
            $canManage = in_array($role, ['owner', 'board_member_manager'], true);
            $canEdit = $canManage || $role === 'board_editor';

            return [
                'id' => $t->id,
                'code' => Task::buildCode($board?->name, $t->id),
                'title' => $t->title,
                'priority' => $t->priority,
                'due_date' => $due?->toDateString(),
                'formatted_due_date' => $due?->format('d/m/Y'),
                'due_group' => $this->dueGroup($due, $today),
                'board_id' => $board?->id,
                'board_name' => $board?->name,
                'can_edit' => $canEdit,
                'can_manage' => $canManage,
                'column_name' => $t->column?->name,
                'status' => $t->status ? [
                    'id' => $t->status->id,
                    'name' => $t->status->name,
                    'color' => $t->status->color,
                    'is_completed' => (bool) $t->status->is_completed,
                ] : null,
                'labels' => $t->labels->map(fn ($l) => [
                    'id' => $l->id, 'name' => $l->name, 'color' => $l->color,
                ])->values(),
            ];
        });

        return Inertia::render('MyTasks/Index', [
            'tasks' => $data->values(),
        ]);
    }

    private function dueGroup(?Carbon $due, Carbon $today): string
    {
        if (! $due) {
            return 'none';
        }
        if ($due->lt($today)) {
            return 'overdue';
        }
        if ($due->eq($today)) {
            return 'today';
        }
        if ($due->lte($today->copy()->addDays(7))) {
            return 'week';
        }

        return 'later';
    }
}
