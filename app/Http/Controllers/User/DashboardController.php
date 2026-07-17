<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BoardTemplate;
use App\Models\Task;
use Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $accessibleBoards = $user->getAllAccessibleBoards();
        $assignedTaskCounts = Task::query()
            ->selectRaw('columns.board_id, COUNT(DISTINCT tasks.id) as assigned_task_count')
            ->join('columns', 'columns.id', '=', 'tasks.column_id')
            ->join('assignees', 'assignees.task_id', '=', 'tasks.id')
            ->where('assignees.user_id', $user->id)
            ->whereIn('columns.board_id', $accessibleBoards->pluck('id'))
            ->groupBy('columns.board_id')
            ->pluck('assigned_task_count', 'columns.board_id');

        $boards = $accessibleBoards->map(function ($board) use ($user, $assignedTaskCounts) {
            return [
                'id' => $board->id,
                'name' => $board->name,
                'currentUserRole' => $user->getRoleForBoard($board),
                'updated_at' => optional($board->updated_at)->format('d/m/Y'),
                'show_url' => route('boards.show', $board->id),
                'assignedTaskCount' => (int) ($assignedTaskCounts[$board->id] ?? 0),
            ];
        })->values();

        $templates = BoardTemplate::orderBy('position')->get()->map(fn ($t) => [
            'key' => $t->id,
            'name' => $t->name,
            'icon' => $t->icon,
            'description' => $t->description,
            'columns' => $t->columns,
        ]);

        return Inertia::render('User/Dashboard', [
            'boards' => $boards,
            'templates' => $templates,
        ]);
    }

    public function store()
    {
    }

    public function update()
    {
    }

    public function destroy()
    {
    }
}
