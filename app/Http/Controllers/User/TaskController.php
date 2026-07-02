<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskRequest;
use App\Http\Requests\UpdateTaskPositionRequest;
use App\Models\Board;
use App\Models\Column;
use App\Models\Task;
use App\Models\TaskHistory;
use Auth;
use DB;
use Log;

class TaskController extends Controller
{
    private function authorizeTaskAccess(?Task $task, array $requiredPermissions = [])
    {
        abort_if(! $task, 404, 'Task not found.');
        $board = $task->column?->board;
        abort_if(! $board, 404, 'Board not found.');
        $user = Auth::user();
        foreach ($requiredPermissions as $permission) {
            if ($user->hasBoardPermission($board, $permission)) {
                return $board;
            }
        }
        abort(403, 'Bạn không có quyền thực hiện thao tác!');
    }

    /**
     * Chặn gán status không thuộc tập status của board.
     * Board chưa cấu hình tập status -> chấp nhận mọi status global (tương thích ngược).
     */
    private function assertStatusAllowed(Board $board, $statusId): void
    {
        if (! $statusId) {
            return;
        }
        $allowed = $board->statuses()->pluck('statuses.id');
        if ($allowed->isEmpty()) {
            return;
        }
        abort_unless($allowed->contains((int) $statusId), 422, 'Trạng thái không thuộc bảng này.');
    }

    /** Shape gọn của trạng thái để trả về JSON. */
    private function statusPayload($status): ?array
    {
        if (! $status) {
            return null;
        }

        return [
            'id' => $status->id,
            'key' => $status->key,
            'name' => $status->name,
            'color' => $status->color,
            'is_completed' => (bool) $status->is_completed,
        ];
    }

    private function authorizeBoardAccess(Board $board, array $requiredPermissions = [])
    {
        $user = Auth::user();
        foreach ($requiredPermissions as $permission) {
            if ($user->hasBoardPermission($board, $permission)) {
                return $board;
            }
        }

        abort(403, 'Bạn không có quyền thực hiện thao tác!');
    }

    public function store(TaskRequest $request, Column $column)
    {
        $board = $column->board;
        $this->authorizeBoardAccess($board, ['board_editor', 'board_member_manager']);
        $this->assertStatusAllowed($board, $request->input('status_id'));

        try {
            $data = $request->validated();
            $data['priority'] = $request->input('priority', 'normal');
            $createdTask = DB::transaction(function () use ($column, $data) {
                $task = (new Task())->createForColumn($column, $data);
                (new TaskHistory())->logTaskHistory($task, 'tạo', null, $column->name);

                return $task;
            });
            $createdTask->load('assignees', 'status');

            return response()->json([
                'success' => true,
                'message' => 'Công việc đã được tạo thành công!',
                'task' => [
                    'id' => $createdTask->id,
                    'title' => $createdTask->title,
                    'description' => $createdTask->description,
                    'priority' => $createdTask->priority,
                    'status' => $this->statusPayload($createdTask->status),
                    'due_date' => $createdTask->due_date ? $createdTask->due_date->toDateString() : null,
                    'formatted_due_date' => $createdTask->due_date ? $createdTask->due_date->format('d M') : null,
                    'position' => $createdTask->position,
                    'column_id' => $createdTask->column_id,
                    'assignees' => $createdTask->assignees->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'avatar_url' => $user->avatar_url ?? 'https://i.pravatar.cc/30?u=' . $user->id,
                        ];
                    }),
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error(
                "Error creating task in column {$column->id}: "
                . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine()
            );

            return response()->json([
                'success' => false,
                'message' => 'Không thể thêm nhiệm vụ mới. Vui lòng thử lại.',
            ], 500);
        }
    }

    public function show(Task $task)
    {
        $board = $this->authorizeTaskAccess($task, ['board_viewer', 'board_editor', 'board_member_manager']);
        $task->loadDetails();

        $this->applyColumnName($task);
        $task->code = Task::buildCode($board->name, $task->id);
        // Định dạng ngày nếu cần
        $task->formatted_due_date = $task->due_date ? $task->due_date->format('d/m/Y') : null;

        $this->applyTaskHistories($task);
        $this->applyComments($task);
        $this->applyChecklists($task);

        return response()->json([
            'success' => true,
            'task' => $task,
        ]);
    }

    private function applyColumnName(Task $task): void
    {
        if ($task->column) {
            $task->column_name = $task->column->name;
        } else {
            $task->column_name = 'N/A';
            Log::warning("Task ID {$task->id} is missing column relation when trying to show details.");
        }
    }

    private function applyTaskHistories(Task $task): void
    {
        if (! ($task->taskHistories instanceof \Illuminate\Database\Eloquent\Collection)) {
            $task->task_histories = collect([]);

            return;
        }

        $task->task_histories = $task->taskHistories()
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
        $task->task_histories->transform(fn ($history) => $this->mapHistory($history));
    }

    private function mapHistory($history): array
    {
        $user = $history->user;

        $timestamp = $history->updated_at ?? $history->created_at;
        $formatted_time = $timestamp
            ? \Carbon\Carbon::parse($timestamp)->format('Y/m/d H:i:s')
            : 'Không rõ thời gian';

        return [
            'id' => $history->id,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Người dùng không xác định',
            'user_avatar' => $user
                ? 'https://i.pravatar.cc/40?u=' . $user->id
                : 'https://i.pravatar.cc/40?u=unknown',
            'action' => $history->action,
            'note' => $history->note,
            'created_at' => $history->created_at->format('Y/m/d H:i:s'),
            'updated_at' => $formatted_time,
        ];
    }

    private function applyComments(Task $task): void
    {
        if (! ($task->comments instanceof \Illuminate\Database\Eloquent\Collection)) {
            $task->comments = collect([]);
            Log::warning("Task ID {$task->id}: comments was not a collection, possibly null or load issue.");

            return;
        }

        $task->comments->transform(function ($comment) {
            $comment->user_name = $comment->user ? $comment->user->name : 'Người dùng không xác định';
            $comment->user_avatar = $comment->user
                ? ('https://i.pravatar.cc/40?u=' . $comment->user->id)
                : 'https://i.pravatar.cc/40?u=unknown';
            $comment->time_ago = $comment->created_at
                ? $comment->created_at->diffForHumans()
                : 'Không rõ thời gian';

            return $comment;
        });
    }

    private function applyChecklists(Task $task): void
    {
        if (! ($task->checklists instanceof \Illuminate\Database\Eloquent\Collection)) {
            $task->checklists = collect([]);
            Log::warning("Task ID {$task->id}: checklists was not a collection, possibly null or load issue.");

            return;
        }

        $task->checklists->transform(fn ($item) => [
            'id' => $item->id,
            'title' => $item->title,
            'is_done' => $item->is_done,
            'position' => $item->position,
            'task_id' => $item->task_id,
        ]);
    }

    /**
     * Trang chỉnh sửa task (page riêng). Nhận mã dạng "ICE-0042" trên URL,
     * suy ngược id rồi render Inertia. Chi tiết đầy đủ do trang tự fetch qua tasks.show.
     */
    public function edit(string $taskCode)
    {
        $task = Task::findOrFail(Task::idFromCode($taskCode));
        // Viewer cũng mở được trang (read-only) — link thông báo/nhắc hạn trỏ về đây.
        $board = $this->authorizeTaskAccess($task, ['board_viewer', 'board_editor', 'board_member_manager']);
        $canEdit = Auth::user()->hasBoardPermission($board, 'board_editor')
            || Auth::user()->hasBoardPermission($board, 'board_member_manager');

        // Ưu tiên tập trạng thái riêng của bảng; nếu bảng chưa gán thì dùng toàn bộ status global.
        $statuses = $board->statuses()
            ->orderBy('statuses.position')
            ->get(['statuses.id', 'statuses.key', 'statuses.name', 'statuses.color', 'statuses.is_completed']);
        if ($statuses->isEmpty()) {
            $statuses = \App\Models\Status::orderBy('position')
                ->get(['id', 'key', 'name', 'color', 'is_completed']);
        }

        return \Inertia\Inertia::render('Tasks/Edit', [
            'taskId' => $task->id,
            'boardId' => $board->id,
            'boardName' => $board->name,
            'code' => Task::buildCode($board->name, $task->id),
            'canEdit' => $canEdit,
            'canManage' => Auth::user()->hasBoardPermission($board, 'board_member_manager'),
            'statuses' => $statuses,
            'boardLabels' => $board->labels()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    /**
     * Update the specified task in storage.
     */
    public function update(TaskRequest $request, Task $task)
    {
        $board = $this->authorizeTaskAccess($task, ['board_member_manager', 'board_editor']);
        $this->assertStatusAllowed($board, $request->input('status_id'));

        try {
            $task->updateDetails($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật thể nhiệm vụ thành công.',
                'task' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'priority' => $task->priority,
                    'status' => $this->statusPayload($task->load('status')->status),
                    'due_date' => $task->due_date ? $task->due_date->toDateString() : null,
                    'formatted_due_date' => $task->due_date ? $task->due_date->format('M d') : null,
                    'position' => $task->position,
                    'column_id' => $task->column_id,
                    'assignees' => $task->assignees->map(function ($assignee) {
                        return ['id' => $assignee->id, 'name' => $assignee->name, 'email' => $assignee->email];
                    }),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Error updating task {$task->id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Không thể cập nhật thể nhiệm vụ.',
            ], 500);
        }
    }

    /**
     * Remove the specified task from storage.
     */
    public function destroy(Task $task)
    {
        $this->authorizeTaskAccess($task, ['board_member_manager']);

        try {
            $task->deleteWithHistory();

            return response()->json(['success' => true, 'message' => 'Xóa nhiệm vụ thành công']);
        } catch (\Exception $e) {
            Log::error("Error deleting task {$task->id}: " . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'không thể xóa nhiệm vụ'], 500);
        }
    }

    /**
     * Update task position (within or between columns).
     */
    public function updatePosition(UpdateTaskPositionRequest $request)
    {
        $task = Task::findOrFail($request->task_id);
        $board = $this->authorizeTaskAccess($task, ['board_editor', 'board_member_manager']);
        $taskHistory = new TaskHistory();
        $oldColumn = Column::find($task->column_id);
        $newColumn = Column::findOrFail($request->new_column_id);
        if ($newColumn->board_id !== $board->id) {
            abort(403, 'Không thể di chuyển nhiệm vụ sang một cột trong bảng khác.');
        }

        try {
            DB::beginTransaction();

            // Cập nhật column_id (chỉ khi khác)
            if ($task->column_id !== $request->new_column_id) {
                $task->column_id = $request->new_column_id;
                $task->save();
            }
            $action = 'di chuyển';
            $taskHistory->logTaskHistory($task, $action, $oldColumn->name ?? null, $newColumn->name ?? null);

            $boardTaskIds = Task::whereHas('column', function ($q) use ($board) {
                $q->where('board_id', $board->id);
            })->pluck('id')->all();

            foreach ($request->order as $index => $taskId) {
                if (! in_array((int) $taskId, $boardTaskIds, true)) {
                    abort(403, 'Nhiệm vụ không thuộc bảng này.');
                }
                Task::where('id', $taskId)->update(['position' => $index]);
            }

            $action = 'Di chuyển';

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Vị trí nhiệm vụ đã cập nhật.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi cập nhật vị trí: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Không thể cập nhật vị trí nhiệm vụ.'], 500);
        }
    }
}
