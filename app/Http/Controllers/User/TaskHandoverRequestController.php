<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Task;
use App\Models\TaskHandoverRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskHandoverRequestController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $from = Auth::user();
        $board = $task->column?->board;
        abort_unless($board && $from->getRoleForBoard($board) === 'board_viewer', 403);
        abort_unless($task->assignees()->whereKey($from->id)->exists(), 403);

        $data = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id', 'different:' . $from->id],
        ]);
        $to = User::findOrFail($data['to_user_id']);
        abort_unless($to->getRoleForBoard($board), 422, 'Người nhận phải là thành viên của bảng.');

        $handover = null;
        DB::transaction(function () use ($task, $from, $to, &$handover) {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
            $handover = TaskHandoverRequest::firstOrCreate([
                'task_id' => $task->id,
                'from_user_id' => $from->id,
                'to_user_id' => $to->id,
                'status' => 'pending',
            ]);

            if ($handover->wasRecentlyCreated) {
                $lockedTask->bumpRevision();
                Notification::notifyUser(
                    $to->id,
                    '<strong>' . e($from->name) . '</strong> muốn bàn giao task '
                    . '<strong>' . e($task->title) . '</strong> cho bạn.',
                    route('tasks.edit', ['taskCode' => $task->code(), 'handover_request' => $handover->id]),
                    $task->id
                );
            }
        });

        return response()->json(['success' => true, 'message' => 'Đã gửi yêu cầu bàn giao.']);
    }

    public function accept(TaskHandoverRequest $handoverRequest)
    {
        $to = Auth::user();

        DB::transaction(function () use ($handoverRequest, $to) {
            $request = TaskHandoverRequest::query()->lockForUpdate()->findOrFail($handoverRequest->id);
            abort_unless(
                $request->status === 'pending' && $request->to_user_id === $to->id,
                409,
                'Yêu cầu bàn giao không còn hiệu lực.'
            );

            $task = Task::query()->lockForUpdate()->findOrFail($request->task_id);
            $board = $task->column?->board;
            abort_unless(
                $board
                && $to->getRoleForBoard($board)
                && $task->assignees()->whereKey($request->from_user_id)->exists(),
                409,
                'Task hoặc quyền thành viên đã thay đổi; không thể nhận bàn giao.'
            );

            $task->assignees()->detach($request->from_user_id);
            $task->assignees()->syncWithoutDetaching([$to->id]);
            $request->update(['status' => 'accepted', 'accepted_at' => now()]);
            TaskHandoverRequest::where('task_id', $request->task_id)
                ->where('from_user_id', $request->from_user_id)
                ->where('status', 'pending')
                ->where('id', '!=', $request->id)
                ->update(['status' => 'cancelled']);
            $task->taskHistories()->create([
                'user_id' => $to->id,
                'action' => 'task_handed_over',
                'note' => e($to->name) . ' đã nhận bàn giao công việc.',
            ]);
            $task->bumpRevision();
        });

        return response()->json(['success' => true, 'message' => 'Bạn đã nhận bàn giao công việc.']);
    }
}
