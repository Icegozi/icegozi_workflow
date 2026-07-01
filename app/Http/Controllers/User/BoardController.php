<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\BoardRequest;
use App\Models\Board;
use App\Models\BoardTemplate;
use App\Models\Column;
use App\Models\Task;
use Auth;
use Carbon\Carbon;
use DB;

class BoardController extends Controller
{
    private function authorizeBoardAccess(Board $board, array $requiredPermissions = [])
    {
        $user = Auth::user();
        // Kiểm tra nếu người dùng có một trong các quyền yêu cầu
        foreach ($requiredPermissions as $permission) {
            if ($user->hasBoardPermission($board, $permission)) {
                return $board;
            }
        }

        abort(403, 'Bạn không có quyền thực hiện thao tác!');
    }

    public function store(BoardRequest $request)
    {
        $validated = $request->validated();
        $template = $request->input('template_id')
            ? BoardTemplate::find($request->input('template_id'))
            : null;

        // Fallback tối thiểu nếu không chọn mẫu (hoặc mẫu đã bị xoá).
        $columns = $template?->columns ?: ['Việc cần làm', 'Đang làm', 'Hoàn thành'];
        $labels = $template?->labels ?: [];
        $statusIds = $template?->status_ids ?: [];

        DB::transaction(function () use ($validated, $columns, $labels, $statusIds) {
            $board = Board::create([
                'user_id' => Auth::id(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            foreach (array_values($columns) as $index => $columnName) {
                Column::create(['name' => $columnName, 'position' => $index, 'board_id' => $board->id]);
            }
            foreach ($labels as $label) {
                $board->labels()->create(['name' => $label['name'] ?? null, 'color' => $label['color'] ?? '#6c757d']);
            }
            if ($statusIds) {
                $board->statuses()->sync($statusIds);
            }
        });

        return redirect()->route('user.dashboard')->with('success', 'Bảng đã được tạo thành công!');
    }

    /** Nhân bản một bảng: copy cột, nhãn và (tuỳ chọn) task + checklist. */
    public function duplicate(Board $board)
    {
        // Chỉ editor/manager (hoặc chủ board) mới được nhân bản — tránh viewer clone toàn bộ dữ liệu.
        $this->authorizeBoardAccess($board, ['board_editor', 'board_member_manager']);
        $withTasks = request()->boolean('with_tasks', true);

        $board->load(['columns', 'labels', 'statuses', 'columns.tasks.checklists', 'columns.tasks.labels']);

        DB::transaction(function () use ($board, $withTasks) {
            $newBoard = Board::create([
                'user_id' => Auth::id(),
                'name' => $board->name . ' (bản sao)',
                'description' => $board->description,
            ]);

            // Sao chép tập trạng thái áp dụng của bảng
            $newBoard->statuses()->sync($board->statuses->pluck('id')->all());

            // Nhãn: map nhãn cũ -> nhãn mới để gắn lại cho task
            $labelMap = [];
            foreach ($board->labels as $label) {
                $new = $newBoard->labels()->create(['name' => $label->name, 'color' => $label->color]);
                $labelMap[$label->id] = $new->id;
            }

            foreach ($board->columns as $column) {
                $newColumn = Column::create([
                    'name' => $column->name,
                    'position' => $column->position,
                    'board_id' => $newBoard->id,
                ]);

                if (! $withTasks) {
                    continue;
                }

                foreach ($column->tasks as $task) {
                    $newTask = Task::create([
                        'title' => $task->title,
                        'description' => $task->description,
                        'priority' => $task->priority,
                        'status_id' => $task->status_id,
                        'column_id' => $newColumn->id,
                        'position' => $task->position,
                        'due_date' => $task->due_date,
                    ]);

                    $newLabelIds = $task->labels->pluck('id')
                        ->map(fn ($id) => $labelMap[$id] ?? null)
                        ->filter()->all();
                    if ($newLabelIds) {
                        $newTask->labels()->sync($newLabelIds);
                    }

                    foreach ($task->checklists as $item) {
                        $newTask->checklists()->create([
                            'title' => $item->title,
                            'is_done' => $item->is_done,
                            'position' => $item->position,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('user.dashboard')->with('success', 'Đã nhân bản bảng thành công!');
    }

    public function show(Board $board)
    {
        $this->authorizeBoardAccess($board, ['board_viewer', 'board_editor', 'board_member_manager']);
        $board->load([
            'columns' => function ($query) {
                $query->orderBy('position', 'asc');
            },
            'labels',
            'columns.tasks' => function ($query) {
                $query->with(['assignees', 'status', 'labels'])
                    ->withCount([
                        'comments',
                        'attachments',
                        'checklists',
                        'checklists as checklists_done_count' => fn ($q) => $q->where('is_done', true),
                    ])
                    ->orderBy('position', 'asc');
            },
        ]);

        $user = Auth::user();
        $canEdit = $user->hasBoardPermission($board, 'board_editor')
            || $user->hasBoardPermission($board, 'board_member_manager');
        $canManage = $user->hasBoardPermission($board, 'board_member_manager');

        return \Inertia\Inertia::render('Boards/Show', [
            'board' => [
                'id' => $board->id,
                'name' => $board->name,
                'labels' => $board->labels->map(fn ($l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'color' => $l->color,
                ])->values(),
                'columns' => $board->columns->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'position' => $c->position,
                    'tasks' => $c->tasks->map(fn ($t) => [
                        'id' => $t->id,
                        'code' => Task::buildCode($board->name, $t->id),
                        'title' => $t->title,
                        'column_id' => $t->column_id,
                        'position' => $t->position,
                        'priority' => $t->priority,
                        'has_description' => filled($t->description),
                        'status' => $t->status ? [
                            'id' => $t->status->id,
                            'key' => $t->status->key,
                            'name' => $t->status->name,
                            'color' => $t->status->color,
                            'is_completed' => (bool) $t->status->is_completed,
                        ] : null,
                        'labels' => $t->labels->map(fn ($l) => [
                            'id' => $l->id,
                            'name' => $l->name,
                            'color' => $l->color,
                        ])->values(),
                        'due_date' => $t->due_date ? $t->due_date->toDateString() : null,
                        'formatted_due_date' => $t->due_date ? $t->due_date->format('d/m/Y') : null,
                        'comments_count' => $t->comments_count,
                        'attachments_count' => $t->attachments_count,
                        'checklist_total' => $t->checklists_count,
                        'checklist_done' => $t->checklists_done_count,
                        'assignees' => $t->assignees->map(fn ($u) => [
                            'id' => $u->id,
                            'name' => $u->name,
                            'email' => $u->email,
                        ])->values(),
                    ])->values(),
                ])->values(),
            ],
            'canEdit' => $canEdit,
            'canManage' => $canManage,
        ]);
    }

    /** Nhật ký hoạt động cấp board: gom TaskHistory của mọi task trong bảng. */
    public function activity(Board $board)
    {
        $this->authorizeBoardAccess($board, ['board_viewer', 'board_editor', 'board_member_manager']);

        $items = \App\Models\TaskHistory::with('user')
            ->whereHas('task.column', fn ($q) => $q->where('board_id', $board->id))
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'action' => $h->action,
                'note' => $h->note,   // đã là HTML dựng sẵn
                'user_name' => $h->user?->name ?? 'Không rõ',
                'user_avatar' => 'https://i.pravatar.cc/40?u=' . ($h->user_id ?? 'x'),
                'time_ago' => $h->created_at?->diffForHumans(),
            ]);

        return response()->json(['success' => true, 'activities' => $items]);
    }

    /** Số liệu phân tích cấp board cho các biểu đồ. */
    public function analytics(Board $board)
    {
        $this->authorizeBoardAccess($board, ['board_viewer', 'board_editor', 'board_member_manager']);

        $tasks = Task::whereHas('column', fn ($q) => $q->where('board_id', $board->id))
            ->with(['status', 'assignees'])
            ->get();

        $overdue = $tasks->filter(fn ($t) => $this->isOverdue($t));

        return response()->json([
            'success' => true,
            'totals' => [
                'tasks' => $tasks->count(),
                'done' => $tasks->filter(fn ($t) => $this->isDone($t))->count(),
                'overdue' => $overdue->count(),
                'members' => count($board->getMembersWithRoles()) + 1,
            ],
            'byStatus' => $this->aggByStatus($tasks),
            'byPriority' => $this->aggByPriority($tasks),
            'workload' => $this->aggWorkload($tasks),
            'overdueByAssignee' => $this->aggOverdueByAssignee($overdue),
            'timeline' => $this->aggTimeline($tasks),
        ]);
    }

    private function isDone(Task $task): bool
    {
        return (bool) $task->status?->is_completed;
    }

    private function isOverdue(Task $task): bool
    {
        return $task->due_date && ! $this->isDone($task)
            && Carbon::parse($task->due_date)->startOfDay()->lt(Carbon::today());
    }

    /** @param \Illuminate\Support\Collection $tasks */
    private function aggByStatus($tasks): array
    {
        return $tasks->groupBy(fn ($t) => $t->status?->name ?? 'Chưa đặt')
            ->map(fn ($group, $name) => [
                'name' => $name,
                'color' => $group->first()->status?->color ?? '#c1c7d0',
                'count' => $group->count(),
            ])->values()->all();
    }

    /** @param \Illuminate\Support\Collection $tasks */
    private function aggByPriority($tasks): array
    {
        $meta = [
            'urgent' => ['Khẩn cấp', '#e5484d'],
            'high' => ['Cao', '#f76808'],
            'normal' => ['Bình thường', '#006adc'],
            'low' => ['Thấp', '#18794e'],
        ];

        return collect($meta)->map(fn ($m, $key) => [
            'label' => $m[0],
            'color' => $m[1],
            'count' => $tasks->where('priority', $key)->count(),
        ])->values()->all();
    }

    /** @param \Illuminate\Support\Collection $tasks */
    private function aggWorkload($tasks): array
    {
        $workload = [];
        foreach ($tasks as $t) {
            foreach ($t->assignees as $u) {
                $workload[$u->id] ??= ['name' => $u->name, 'done' => 0, 'pending' => 0];
                $this->isDone($t) ? $workload[$u->id]['done']++ : $workload[$u->id]['pending']++;
            }
        }

        return array_values($workload);
    }

    /** @param \Illuminate\Support\Collection $overdueTasks */
    private function aggOverdueByAssignee($overdueTasks): array
    {
        $result = [];
        $bump = function ($key, $name) use (&$result) {
            $result[$key] = ['name' => $name, 'count' => ($result[$key]['count'] ?? 0) + 1];
        };
        foreach ($overdueTasks as $t) {
            if ($t->assignees->isEmpty()) {
                $bump('__none', 'Chưa giao');
            }
            foreach ($t->assignees as $u) {
                $bump($u->id, $u->name);
            }
        }

        return array_values($result);
    }

    /** @param \Illuminate\Support\Collection $tasks */
    private function aggTimeline($tasks): array
    {
        $n = (int) request('days', 14);
        $n = in_array($n, [7, 14, 30, 90], true) ? $n : 14;
        $today = Carbon::today();
        $keys = collect(range($n - 1, 0))->map(fn ($i) => $today->copy()->subDays($i)->format('Y-m-d'));

        // Gom đếm theo ngày 1 lần (O(tasks)) thay vì lồng filter theo từng ngày.
        $createdMap = [];
        $completedMap = [];
        foreach ($tasks as $t) {
            if ($t->created_at) {
                $k = $t->created_at->format('Y-m-d');
                $createdMap[$k] = ($createdMap[$k] ?? 0) + 1;
            }
            if ($this->isDone($t) && $t->updated_at) {
                $k = $t->updated_at->format('Y-m-d');
                $completedMap[$k] = ($completedMap[$k] ?? 0) + 1;
            }
        }

        return [
            'labels' => $keys->map(fn ($k) => Carbon::parse($k)->format('d/m'))->all(),
            'created' => $keys->map(fn ($k) => $createdMap[$k] ?? 0)->all(),
            'completed' => $keys->map(fn ($k) => $completedMap[$k] ?? 0)->all(),
        ];
    }

    public function update(BoardRequest $request, Board $board)
    {
        $this->authorizeBoardAccess($board, ['board_editor', 'board_member_manager']);

        $validated = $request->validated();

        $board->update(['name' => $validated['name']]);

        return redirect()->back()->with('success', 'Tên bảng đã được cập nhật.');
    }

    public function destroy(Board $board)
    {
        $this->authorizeBoardAccess($board, ['board_member_manager']);

        $board->delete();

        return redirect()->route('user.dashboard')->with('success', 'Bảng đã được xoá thành công.');
    }
}
