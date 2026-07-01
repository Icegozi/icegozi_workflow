<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Task;
use App\Models\TaskHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommentController extends Controller
{
    private function authorizeTaskAccess(?Task $task, array $requiredPermissions = [])
    {
        abort_if(! $task, 404, 'Không tìm thấy công việc.');
        $board = $task->column?->board;
        abort_if(! $board, 404, 'Không tìm thấy bảng.');
        $user = Auth::user();
        foreach ($requiredPermissions as $permission) {
            if ($user->hasBoardPermission($board, $permission)) {
                return $board;
            }
        }
        abort(403, 'Bạn không có quyền thực hiện thao tác!');
    }

    public function store(Request $request, Task $task)
    {
        $board = $this->authorizeTaskAccess($task, ['board_viewer', 'board_editor', 'board_member_manager']);
        $request->validate([
            'content' => 'required|string|max:5000',
            'mentions' => 'nullable|array',
            'mentions.*' => 'integer',
        ]);

        try {
            $comment = DB::transaction(function () use ($task, $request) {
                $newComment = $task->comments()->create([
                    'user_id' => Auth::id(),
                    'content' => $request->content,
                ]);

                (new TaskHistory())->logTaskHistory($task, 'thêm bình luận');

                return $newComment;
            });
            $comment->load('user');

            // Thông báo mention không được làm hỏng việc thêm bình luận (đã commit).
            try {
                $this->notifyMentions($task, $board, (array) $request->input('mentions', []));
            } catch (\Throwable $e) {
                Log::warning("notifyMentions failed for task {$task->id}: " . $e->getMessage());
            }

            $comment->user_avatar = $comment->user
                ? ('https://i.pravatar.cc/40?u=' . $comment->user->id)
                : 'https://i.pravatar.cc/40?u=unknown';

            return response()->json([
                'success' => true, 'message' => 'Bình luận đã được thêm.',
                'comment' => $comment,
            ]);
        } catch (\Exception $e) {
            Log::error("Error adding comment to task {$task->id}: " . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Không thể thêm bình luận.'], 500);
        }
    }

    /** Tạo thông báo cho những thành viên được nhắc (@) trong bình luận. */
    private function notifyMentions(Task $task, $board, array $mentionIds): void
    {
        $mentionIds = array_filter(array_map('intval', $mentionIds));
        if (empty($mentionIds)) {
            return;
        }

        $me = Auth::id();
        $meName = e(Auth::user()->name);

        // Chỉ nhắc thành viên hợp lệ của bảng (đã gồm chủ bảng), bỏ chính mình.
        $memberIds = collect((new \App\Models\Board())->getAssignedUsersByBoardId($board->id))
            ->pluck('id')->all();

        $code = Task::buildCode($board->name, $task->id);
        $url = route('tasks.edit', $code, false);
        // Escape tiêu đề (message render bằng v-html ở chuông thông báo).
        $msg = "<strong>{$meName}</strong> đã nhắc bạn trong <strong>{$code}</strong> — " . e($task->title) . '.';

        foreach (array_unique($mentionIds) as $uid) {
            if ($uid !== $me && in_array($uid, $memberIds, true)) {
                Notification::notifyUser($uid, $msg, $url, $task->id);
            }
        }
    }

    public function destroy(Task $task, $commentId)
    {
        // Mọi thành viên của bảng đều có thể truy cập; quyền xoá được kiểm tra bên dưới.
        $board = $this->authorizeTaskAccess($task, ['board_viewer', 'board_editor', 'board_member_manager']);
        try {
            $comment = $task->comments()->findOrFail($commentId);

            // Chỉ tác giả bình luận hoặc người quản lý thành viên mới được xoá.
            $isAuthor = $comment->user_id === Auth::id();
            $isManager = Auth::user()->hasBoardPermission($board, 'board_member_manager');
            if (! $isAuthor && ! $isManager) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa bình luận này.',
                ], 403);
            }

            DB::transaction(function () use ($comment, $task) {
                $comment->delete();
                (new TaskHistory())->logTaskHistory($task, 'xóa bình luận');
            });

            return response()->json([
                'success' => true,
                'message' => 'Bình luận đã được xóa.',
            ]);
        } catch (\Exception $e) {
            Log::error("Error deleting comment for task {$task->id}: " . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Không thể xóa bình luận.'], 500);
        }
    }
}
