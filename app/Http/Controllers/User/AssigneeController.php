<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssigneeRequest;
use App\Models\Assignee;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Auth;
use Illuminate\Support\Facades\DB;

class AssigneeController extends Controller
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

    private function isBoardMember(Board $board, int $userId): bool
    {
        if ($board->user_id === $userId) {
            return true;
        }
        $target = User::find($userId);

        return $target
            && ($target->hasBoardPermission($board, 'board_viewer')
                || $target->hasBoardPermission($board, 'board_editor')
                || $target->hasBoardPermission($board, 'board_member_manager'));
    }

    public function store(AssigneeRequest $request, Task $task)
    {
        $board = $this->authorizeTaskAccess($task, ['board_member_manager']);
        $assignee = new Assignee();
        $userId = $request->user_id;
        if (! $this->isBoardMember($board, (int) $userId)) {
            return $this->response(false, 'Người dùng không thuộc bảng này.', $task, 422);
        }
        if ($assignee->isExistsAsignee($userId, $task->id)) {
            return $this->response(false, 'Người dùng đã được giao nhiệm vụ này.', $task, 409);
        }

        $assignee->addAsignee($userId, $task->id);

        return $this->response(true, 'Người dùng đã được chỉ định thành công.', $task->fresh());
    }

    public function update(AssigneeRequest $request, Task $task, User $user)
    {
        $board = $this->authorizeTaskAccess($task, ['board_member_manager']);
        $assignee = new Assignee();
        $newUserId = (int) $request->user_id;
        if (! $this->isBoardMember($board, $newUserId)) {
            return $this->response(false, 'Người dùng không thuộc bảng này.', $task, 422);
        }

        $updatedAssignee = $assignee->updateUserForTask($task->id, $user->id, $newUserId);
        if (! $updatedAssignee) {
            return $this->response(false, 'Cập nhật người phụ trách không thành công.', $task, 500);
        }

        return $this->response(true, 'Người phụ trách đã được cập nhật thông tin.', $task->fresh());
    }

    public function destroy(Task $task, User $user)
    {
        $this->authorizeTaskAccess($task, ['board_member_manager']);
        $assignee = new Assignee();
        if (! $assignee->isExistsAsignee($user->id, $task->id)) {
            return $this->response(false, 'Người dùng không được giao nhiệm vụ này.', $task, 404);
        }

        DB::transaction(function () use ($assignee, $task, $user) {
            $assignee->removeAsignee($user->id, $task->id);
            $this->logUnassignHistory($task, $user);
        });

        return $this->response(true, 'Xóa người phụ trách thành công!', $task->fresh());
    }

    private function response(bool $success, string $message, Task $task, int $status = 200)
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'task' => $this->formatTask($task),
        ], $status);
    }

    private function logUnassignHistory(Task $task, User $user): void
    {
        $task->taskHistories()->create([
            'user_id' => Auth::id(),
            'action' => 'assignee_removed',
            'note' => sprintf(
                '%s đã chọn %s phụ trách cho công việc "%s".',
                e(Auth::user()->name),
                e($user->name),
                e($task->title)
            ),
        ]);
    }

    private function formatTask(Task $task)
    {
        $task->load('assignees', 'column');

        $task->assignees = $task->assignees->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url ?? 'https://i.pravatar.cc/30?u=' . $user->id,
        ]);

        $task->column_name = $task->column->name ?? '';
        $task->formatted_due_date = optional($task->due_date)->format('d/m/Y');

        return $task;
    }

    public function assignedUsers(Board $board)
    {
        $users = $board->assignedUsers($board)->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url ?? 'https://i.pravatar.cc/100?u=' . $user->id,
            ];
        });

        return response()->json([
            'success' => true,
            'board_id' => $board->id,
            'users' => $users,
        ]);
    }
}
