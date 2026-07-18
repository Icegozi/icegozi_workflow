<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\Task;
use Auth;
use DB;
use Illuminate\Http\Request;
use Log;

class ChecklistController extends Controller
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

    public function index(Task $task)
    {
        $this->authorizeTaskAccess($task, ['board_viewer', 'board_editor', 'board_member_manager']);
        $checklists = $task->checklists()->orderBy('position')->get();

        return response()->json(['success' => true, 'checklists' => $checklists]);
    }

    public function store(Request $request, Task $task)
    {
        $this->authorizeTaskAccess($task, ['board_editor', 'board_member_manager']);

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        try {
            $checklist = DB::transaction(function () use ($task, $request) {
                $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
                $maxPosition = $lockedTask->checklists()->max('position');
                $position = is_null($maxPosition) ? 0 : $maxPosition + 1;

                $checklist = $lockedTask->checklists()->create([
                    'title' => $request->title,
                    'position' => $position,
                    'is_done' => false,
                ]);

                $lockedTask->taskHistories()->create([
                    'user_id' => Auth::id(),
                    'action' => 'added_checklist_item',
                    'note' => "Đã thêm mục checklist: '" . e($checklist->title) . "'",
                ]);
                $lockedTask->bumpRevision();

                return $checklist;
            });

            return response()->json([
                'success' => true,
                'message' => 'Mục checklist đã được thêm.',
                'checklist' => $checklist,
            ], 201);
        } catch (\Exception $e) {
            Log::error("Error creating checklist for task {$task->id}: " . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Không thể thêm mục checklist.'], 500);
        }
    }

    public function update(Request $request, Checklist $checklist)
    {
        $task = $checklist->task;
        // Người chỉ có quyền xem được phép tích/bỏ tích (is_done); đổi tiêu đề cần editor trở lên.
        if ($request->has('title')) {
            $this->authorizeTaskAccess($task, ['board_editor', 'board_member_manager']);
        } else {
            $this->authorizeTaskAccess($task, ['board_viewer', 'board_editor', 'board_member_manager']);
        }

        // VALIDATION:
        $request->validate([
            'title' => 'sometimes|required_without:is_done|string|max:255',
            'is_done' => 'sometimes|required_without:title|boolean', // Ensures 'is_done' is boolean
        ]);

        try {
            $checklist = DB::transaction(function () use ($checklist, $request) {
                $lockedChecklist = Checklist::query()->lockForUpdate()->findOrFail($checklist->id);
                $lockedTask = Task::query()->lockForUpdate()->findOrFail($lockedChecklist->task_id);
                $messages = $this->applyChecklistChanges($lockedChecklist, $request);

                if (! empty($messages)) {
                    $lockedChecklist->save();
                    $lockedTask->taskHistories()->create([
                        'user_id' => Auth::id(),
                        'action' => 'updated_checklist_item',
                        'note' => 'Đã cập nhật mục checklist: ' . implode(', ', $messages),
                    ]);
                    $lockedTask->bumpRevision();
                }

                return $lockedChecklist;
            });

            // Return the fresh checklist item (with potentially updated values)
            return response()->json([
                'success' => true,
                'message' => 'Mục checklist đã được cập nhật.',
                'checklist' => $checklist->fresh(),
            ]);
        } catch (\Exception $e) {
            // Log the detailed error
            Log::error(
                "Error updating checklist {$checklist->id}: " . $e->getMessage()
                . ' Stack trace: ' . $e->getTraceAsString()
            );

            // Generic 500
            return response()->json([
                'success' => false,
                'message' => 'Không thể cập nhật mục checklist.',
            ], 500);
        }
    }

    private function applyChecklistChanges(Checklist $checklist, Request $request): array
    {
        $originalTitle = $checklist->title;
        $updatedFieldsMessages = [];

        // Check if 'title' is present in the request and different
        if ($request->has('title') && $checklist->title !== $request->title) {
            $checklist->title = $request->title; // Uses validated data if 'title' was part of it
            $updatedFieldsMessages[] = "tiêu đề từ '" . e($originalTitle) . "' thành '" . e($request->title) . "'";
        }

        // Check if 'is_done' is present in the request and different
        // IMPORTANT: Use $request->boolean('is_done') for proper boolean conversion from request
        if ($request->has('is_done') && $checklist->is_done !== $request->boolean('is_done')) {
            $checklist->is_done = $request->boolean('is_done'); // Assign the boolean value
            $status = $checklist->is_done ? 'hoàn thành' : 'chưa hoàn thành';
            $titleForMessage = $request->has('title') ? $request->title : $originalTitle;
            $updatedFieldsMessages[] = "trạng thái mục '" . e($titleForMessage) . "' thành {$status}";
        }

        return $updatedFieldsMessages;
    }

    public function destroy(Checklist $checklist)
    {
        $task = $checklist->task;
        $this->authorizeTaskAccess($task, ['board_editor', 'board_member_manager']);

        try {
            $checklistTitle = $checklist->title;

            DB::transaction(function () use ($checklist, $task, $checklistTitle) {
                $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
                $lockedChecklist = Checklist::query()->lockForUpdate()->findOrFail($checklist->id);
                $lockedChecklist->delete();
                $lockedTask->taskHistories()->create([
                    'user_id' => Auth::id(),
                    'action' => 'deleted_checklist_item',
                    'note' => "Đã xóa mục checklist: '" . e($checklistTitle) . "'",
                ]);
                $lockedTask->bumpRevision();
            });

            return response()->json(['success' => true, 'message' => 'Mục checklist đã được xóa.']);
        } catch (\Exception $e) {
            Log::error("Error deleting checklist {$checklist->id}: " . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Không thể xóa mục checklist.'], 500);
        }
    }

    public function reorder(Request $request, Task $task)
    {
        $this->authorizeTaskAccess($task, ['board_editor', 'board_member_manager']);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:checklists,id',
            'revision' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($request, $task) {
                $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
                if ($lockedTask->revision !== (int) $request->revision) {
                    abort(response()->json([
                        'success' => false,
                        'code' => 'STALE_VERSION',
                        'message' => 'Dữ liệu công việc đã thay đổi. Vui lòng tải lại trước khi sắp xếp.',
                        'current' => ['revision' => $lockedTask->revision],
                    ], 409));
                }
                $checklistIds = $lockedTask->checklists()->lockForUpdate()->pluck('id')->sort()->values()->all();
                $requestedIds = collect($request->ids)->map(fn ($id) => (int) $id)->sort()->values()->all();
                abort_unless($checklistIds === $requestedIds, 422, 'Danh sách checklist không hợp lệ.');
                foreach ($request->ids as $index => $checklistId) {
                    Checklist::where('id', $checklistId)
                        ->where('task_id', $lockedTask->id)
                        ->update(['position' => $index]);
                }

                $lockedTask->taskHistories()->create([
                    'user_id' => Auth::id(),
                    'action' => 'reordered_checklist',
                    'note' => 'Đã sắp xếp lại các mục trong checklist.',
                ]);
                $lockedTask->bumpRevision();
            });

            return response()->json(['success' => true, 'message' => 'Thứ tự checklist đã được cập nhật.']);
        } catch (\Exception $e) {
            Log::error("Error reordering checklists for task {$task->id}: " . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Không thể cập nhật thứ tự checklist.'], 500);
        }
    }
}
