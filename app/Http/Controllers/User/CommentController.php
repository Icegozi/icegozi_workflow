<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyTaskMentions;
use App\Models\Task;
use App\Models\TaskHistory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        // Chỉ chuẩn hoá chuỗi. Ép mảng/object sang string trước validate sẽ khiến
        // payload sai kiểu trở thành "Array" thay vì nhận lỗi 422.
        if (is_string($request->input('content'))) {
            $request->merge(['content' => trim($request->input('content'))]);
        }
        $request->validate([
            'content' => 'required|string|max:5000',
            'mentions' => 'nullable|array|max:50',
            'mentions.*' => 'integer|distinct',
        ]);

        try {
            $comment = DB::transaction(function () use ($task, $request) {
                $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
                $newComment = $lockedTask->comments()->create([
                    'user_id' => Auth::id(),
                    'content' => $request->content,
                ]);

                (new TaskHistory())->logTaskHistory($lockedTask, 'thêm bình luận');
                $lockedTask->bumpRevision();

                return $newComment;
            });
            $comment->load('user');

            // Chạy sau commit để lỗi/retry notification không làm mất bình luận.
            NotifyTaskMentions::dispatch(
                $comment->id,
                $task->id,
                $board->id,
                Auth::id(),
                $request->input('content'),
                (array) $request->input('mentions', [])
            );

            $comment->user_avatar = $comment->user_avatar_url;
            $comment->can_delete = true;

            return response()->json([
                'success' => true, 'message' => 'Bình luận đã được thêm.',
                'comment' => $comment,
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Công việc không tồn tại hoặc đã bị xóa.',
            ], 404);
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
                $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
                $lockedComment = $lockedTask->comments()->lockForUpdate()->findOrFail($comment->id);
                $lockedComment->delete();
                (new TaskHistory())->logTaskHistory($lockedTask, 'xóa bình luận');
                $lockedTask->bumpRevision();
            });

            return response()->json([
                'success' => true,
                'message' => 'Bình luận đã được xóa.',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bình luận không tồn tại hoặc đã bị xóa.',
            ], 404);
        } catch (\Exception $e) {
            Log::error("Error deleting comment for task {$task->id}: " . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Không thể xóa bình luận.'], 500);
        }
    }
}
