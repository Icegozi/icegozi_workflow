<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Task;
use App\Models\TaskHistory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

            // Thông báo mention không được làm hỏng việc thêm bình luận (đã commit).
            try {
                $this->notifyMentions(
                    $task,
                    $board,
                    $request->input('content'),
                    (array) $request->input('mentions', [])
                );
            } catch (\Throwable $e) {
                Log::warning("notifyMentions failed for task {$task->id}: " . $e->getMessage());
            }

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

    /** Tạo thông báo cho những thành viên được nhắc (@) trong bình luận. */
    private function notifyMentions(Task $task, $board, string $content, array $mentionIds): void
    {
        $mentionIds = array_filter(array_map('intval', $mentionIds));
        if (empty($mentionIds)) {
            return;
        }

        $me = Auth::id();
        $meName = e(Auth::user()->name);

        // Chỉ nhắc thành viên hợp lệ của bảng (đã gồm chủ bảng), bỏ chính mình.
        $members = collect((new \App\Models\Board())->getAssignedUsersByBoardId($board->id))
            ->keyBy('id');

        $code = Task::buildCode($board->name, $task->id);
        $url = route('tasks.edit', $code, false);
        // Escape tiêu đề (message render bằng v-html ở chuông thông báo).
        $msg = "<strong>{$meName}</strong> đã nhắc bạn trong <strong>{$code}</strong> — " . e($task->title) . '.';

        foreach (array_unique($mentionIds) as $uid) {
            $member = $members->get($uid);
            // IDs từ client chỉ là gợi ý; notification chỉ hợp lệ khi nội dung
            // thực sự có token @Tên theo đúng format mà UI chèn vào comment.
            if ($uid !== $me && $member && Str::contains($content, '@' . $member['name'])) {
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
