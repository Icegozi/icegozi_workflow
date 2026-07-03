<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Concerns\GrantsBoardPermission;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAccessRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

/**
 * Luồng "xin quyền xem công việc": người không có quyền mở permalink /tasks/{id}
 * sẽ được đưa tới đây để gửi yêu cầu tới chủ sở hữu bảng (in-app notification + email).
 * Owner cấp quyền board_viewer chỉ với 1 chạm qua signed URL.
 */
class TaskAccessController extends Controller
{
    use GrantsBoardPermission;

    /** Board của task (hoặc 404 nếu task mồ côi). */
    private function boardOf(Task $task)
    {
        $board = $task->column?->board;
        abort_if(! $board, 404, 'Không tìm thấy công việc.');

        return $board;
    }

    private function canView(User $user, $board): bool
    {
        return $user->hasBoardPermission($board, 'board_viewer')
            || $user->hasBoardPermission($board, 'board_editor')
            || $user->hasBoardPermission($board, 'board_member_manager');
    }

    public function requestForm(Task $task)
    {
        $board = $this->boardOf($task);

        // Đã có quyền thì vào thẳng công việc, không cần xin.
        if ($this->canView(Auth::user(), $board)) {
            return redirect()->route('tasks.show', $task->id);
        }

        $owner = User::find($board->user_id);

        return Inertia::render('Tasks/RequestAccess', [
            'taskId' => $task->id,
            'boardId' => $board->id,
            'boardName' => $board->name,
            'taskCode' => Task::buildCode($board->name, $task->id),
            'ownerName' => $owner?->name ?? 'chủ sở hữu bảng',
        ]);
    }

    public function submitRequest(Request $request, Task $task)
    {
        $board = $this->boardOf($task);
        $requester = Auth::user();

        if ($this->canView($requester, $board)) {
            return redirect()->route('tasks.show', $task->id);
        }

        $data = $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $owner = User::find($board->user_id);
        abort_if(! $owner, 404, 'Không tìm thấy chủ sở hữu bảng.');

        $code = Task::buildCode($board->name, $task->id);
        $note = $data['note'] ?? null;

        // Signed URL (7 ngày) để owner cấp quyền xem 1 chạm.
        $grantUrl = URL::temporarySignedRoute(
            'tasks.grant',
            now()->addDays(7),
            ['task' => $task->id, 'requester' => $requester->id]
        );

        // In-app notification cho owner; dedupe theo ngày để tránh spam khi bấm gửi nhiều lần.
        Notification::notifyUser(
            $owner->id,
            "{$requester->name} xin quyền xem công việc {$code} trong bảng '{$board->name}'.",
            $grantUrl,
            $task->id,
            true
        );

        // Email cho owner.
        $owner->notify(new TaskAccessRequestNotification(
            $task,
            $requester,
            $board->name,
            $code,
            $note,
            $grantUrl
        ));

        return redirect()->route('tasks.request-access', $task->id)
            ->with('success', 'Đã gửi yêu cầu tới chủ sở hữu bảng. Bạn sẽ nhận được thông báo khi được cấp quyền.');
    }

    /**
     * Owner (hoặc quản lý thành viên) bấm link đã ký để cấp board_viewer cho người xin.
     * Route đã qua middleware 'signed'.
     */
    public function grant(Task $task, User $requester)
    {
        $board = $this->boardOf($task);

        // Chỉ owner/quản lý thành viên mới được cấp (chống link bị chuyển tiếp cho người khác).
        abort_unless(
            Auth::user()->hasBoardPermission($board, 'board_member_manager'),
            403,
            'Bạn không có quyền cấp quyền cho bảng này.'
        );

        $granted = $this->grantBoardPermission($board, $requester, 'board_viewer');
        abort_unless($granted, 500, 'Không thể cấp quyền.');

        // Báo cho người xin biết đã được cấp quyền, kèm link tới công việc.
        Notification::notifyUser(
            $requester->id,
            "Bạn đã được cấp quyền xem bảng '{$board->name}'.",
            route('tasks.show', $task->id),
            $task->id
        );

        return redirect()->route('boards.show', $board->id)
            ->with('success', "Đã cấp quyền xem cho {$requester->name}.");
    }
}
