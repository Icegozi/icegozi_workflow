<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
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
        $this->authorizeTaskAccess($task, ['board_viewer', 'board_editor', 'board_member_manager']);
        $request->validate(['content' => 'required|string|max:5000']);

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
