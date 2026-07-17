<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskRequest;
use App\Http\Requests\UpdateTaskPositionRequest;
use App\Models\Board;
use App\Models\Column;
use App\Models\Task;
use App\Models\TaskHistory;
use App\Models\TaskHandoverRequest;
use Auth;
use DB;
use Illuminate\Http\Request;
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

    public function show(Request $request, $task)
    {
        // Resolve thủ công (không dùng route-model-binding) để tự xử lý task không tồn tại
        // thay vì trả 404 cứng: permalink -> về dashboard; XHR JSON -> 404.
        $task = Task::find($task);

        // Truy cập bằng trình duyệt (không phải XHR): chuyển sang URL đẹp
        // /b-{board_code}/tasks/{task_code}; task không còn -> dashboard.
        if (! $request->expectsJson()) {
            $board = $task?->column?->board;
            if (! $board) {
                return redirect()->route('dashboard');
            }

            return redirect()->route('tasks.permalink', [
                'boardCode' => $board->board_code,
                'taskCode' => $task->task_code,
            ]);
        }

        $board = $this->authorizeTaskAccess($task, ['board_viewer', 'board_editor', 'board_member_manager']);
        $task->loadDetails();

        $this->applyColumnName($task);
        $task->code = Task::buildCode($board->name, $task->id);
        // Mã hiển thị: board_code + task_code (số thật trong DB), tách với code dùng cho URL.
        $task->display_code = $board->board_code . '-' . $task->task_code;
        // board_code để client dựng permalink /b-{board_code}/tasks/{task_code}.
        $task->board_code = $board->board_code;
        // Định dạng ngày nếu cần
        $task->formatted_due_date = $task->due_date ? $task->due_date->format('d/m/Y') : null;

        $this->applyTaskHistories($task);
        $this->applyComments($task);
        $this->applyChecklists($task);
        $task->incoming_handover_requests = TaskHandoverRequest::query()
            ->where('task_id', $task->id)
            ->where('to_user_id', Auth::id())
            ->where('status', 'pending')
            ->with('fromUser:id,name,email')
            ->get()
            ->map(fn ($handover) => [
                'id' => $handover->id,
                'from_name' => $handover->fromUser?->name,
                'from_email' => $handover->fromUser?->email,
            ]);

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
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
        $task->task_histories->transform(fn ($history) => $this->mapHistory($history));

        // Bỏ quan hệ thô: khi serialize, quan hệ 'taskHistories' (-> key 'task_histories')
        // sẽ ghi đè attribute đã map/format ở trên, làm lộ timestamp ISO thô.
        $task->unsetRelation('taskHistories');
    }

    private function mapHistory($history): array
    {
        $user = $history->user;

        $timestamp = $history->updated_at ?? $history->created_at;
        $formatted_time = $timestamp
            ? \Carbon\Carbon::parse($timestamp)->format('Y-m-d H:i:s')
            : 'Không rõ thời gian';

        return [
            'id' => $history->id,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Người dùng không xác định',
            'user_avatar' => $user?->avatar_url
                ?: ('https://i.pravatar.cc/40?u=' . ($user?->id ?? 'unknown')),
            'action' => $history->action,
            'note' => $history->note,
            'created_at' => $history->created_at->format('Y-m-d H:i:s'),
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
            // Ưu tiên avatar profile của user; không có thì fallback pravatar.
            $comment->user_avatar = $comment->user?->avatar_url
                ?: ('https://i.pravatar.cc/40?u=' . ($comment->user?->id ?? 'unknown'));
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

        return $this->renderTaskPage($task, $board);
    }

    /**
     * Permalink đẹp: /b-{board_code}/tasks/{task_code}. Tra cứu theo mã board + mã task
     * (task_code là số tăng dần TRONG board). Không tìm thấy -> về dashboard.
     */
    public function permalinkShow($boardCode, $taskCode)
    {
        $board = Board::where('board_code', $boardCode)->first();
        if (! $board) {
            return redirect()->route('dashboard');
        }

        // Tra theo board_id trực tiếp (có index + unique với task_code) thay vì join whereHas.
        // orderBy('id') chỉ là phòng thủ cho dữ liệu cũ trước khi có ràng buộc unique.
        $task = Task::where('board_id', $board->id)
            ->where('task_code', $taskCode)
            ->orderBy('id')
            ->first();
        if (! $task) {
            return redirect()->route('dashboard');
        }

        return $this->renderTaskView($task, $board);
    }

    /**
     * Trang XEM chi tiết (chỉ đọc, không phải form). Có quyền -> trang View;
     * không quyền -> chuyển tới form xin quyền chủ sở hữu.
     */
    private function renderTaskView(Task $task, Board $board)
    {
        $user = Auth::user();
        $canView = $user->hasBoardPermission($board, 'board_viewer')
            || $user->hasBoardPermission($board, 'board_editor')
            || $user->hasBoardPermission($board, 'board_member_manager');

        if (! $canView) {
            return redirect()->route('tasks.request-access', $task->id);
        }

        // Ai có quyền sửa sẽ thấy nút "Chỉnh sửa" trỏ sang trang Tasks/Edit.
        $canEdit = $user->hasBoardPermission($board, 'board_editor')
            || $user->hasBoardPermission($board, 'board_member_manager');

        return \Inertia\Inertia::render('Tasks/View', [
            'taskId' => $task->id,
            'boardId' => $board->id,
            'boardName' => $board->name,
            'boardCode' => $board->board_code,
            'taskCode' => $task->task_code,
            'code' => Task::buildCode($board->name, $task->id),
            'displayCode' => $board->board_code . '-' . $task->task_code,
            'canEdit' => $canEdit,
        ]);
    }

    /** Dựng trang Inertia Tasks/Edit cho một task đã xác thực quyền. */
    private function renderTaskPage(Task $task, Board $board)
    {
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
            'boardCode' => $board->board_code,
            'taskCode' => $task->task_code,
            'code' => Task::buildCode($board->name, $task->id),
            'displayCode' => $board->board_code . '-' . $task->task_code,
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
