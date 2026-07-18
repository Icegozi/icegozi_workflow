<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Label;
use App\Models\Task;
use Auth;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    private function authorizeBoardAccess(Board $board, array $requiredPermissions = [])
    {
        $user = Auth::user();
        foreach ($requiredPermissions as $permission) {
            if ($user->hasBoardPermission($board, $permission)) {
                return $board;
            }
        }

        abort(403, 'Bạn không có quyền thực hiện thao tác!');
    }

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
        abort(403, 'Bạn không có quyền thực hiện thao tác!');
    }

    /** Danh sách nhãn của board. */
    public function index(Board $board)
    {
        $this->authorizeBoardAccess($board, ['board_viewer', 'board_editor', 'board_member_manager']);

        return response()->json([
            'success' => true,
            'labels' => $board->labels()->orderBy('name')->get(['id', 'name', 'color', 'revision']),
        ]);
    }

    /** Tạo nhãn mới cho board. */
    public function store(Request $request, Board $board)
    {
        $this->authorizeBoardAccess($board, ['board_editor', 'board_member_manager']);

        $data = $request->validate([
            'name' => 'nullable|string|max:50',
            'color' => 'required|string|max:20',
        ]);

        $label = $board->labels()->create($data);

        return response()->json([
            'success' => true,
            'label' => $label->only(['id', 'name', 'color', 'revision']),
        ], 201);
    }

    /** Xoá nhãn khỏi board (gỡ khỏi mọi task do cascade). */
    public function destroy(Request $request, Label $label)
    {
        $this->authorizeBoardAccess($label->board, ['board_editor', 'board_member_manager']);
        $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $deleted = \DB::transaction(function () use ($request, $label) {
            $locked = Label::query()->lockForUpdate()->findOrFail($label->id);
            if ((int) $locked->revision !== (int) $request->integer('revision')) {
                return false;
            }
            // Xoá nhãn sẽ làm thay đổi mọi task đang gắn nhãn; invalidates revision của chúng.
            $locked->tasks()->select('tasks.id')->lockForUpdate()->get()->each->bumpRevision();
            $locked->delete();

            return true;
        });
        if (! $deleted) {
            return response()->json([
                'success' => false,
                'code' => 'STALE_VERSION',
                'message' => 'Nhãn đã được người khác cập nhật. Vui lòng tải lại.',
            ], 409);
        }

        return response()->json(['success' => true]);
    }

    /** Gắn nhãn vào task. */
    public function attach(Request $request, Task $task)
    {
        $board = $this->authorizeTaskAccess($task, ['board_editor', 'board_member_manager']);
        $data = $request->validate(['label_id' => 'required|exists:labels,id']);

        $label = Label::findOrFail($data['label_id']);
        abort_if($label->board_id !== $board->id, 403, 'Nhãn không thuộc bảng này.');

        \DB::transaction(function () use ($task, $label) {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
            $lockedTask->labels()->syncWithoutDetaching([$label->id]);
            $lockedTask->bumpRevision();
        });

        return response()->json(['success' => true]);
    }

    /** Gỡ nhãn khỏi task. */
    public function detach(Task $task, Label $label)
    {
        $this->authorizeTaskAccess($task, ['board_editor', 'board_member_manager']);
        \DB::transaction(function () use ($task, $label) {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
            $lockedTask->labels()->detach($label->id);
            $lockedTask->bumpRevision();
        });

        return response()->json(['success' => true]);
    }
}
