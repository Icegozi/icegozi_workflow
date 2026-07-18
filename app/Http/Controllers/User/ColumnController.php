<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Column;
use Auth;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ColumnController extends Controller
{
    // Authorization Helper
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

    /**
     * Store a newly created column in storage.
     */
    public function store(Request $request, Board $board)
    {
        $this->authorizeBoardAccess($board, ['board_editor', 'board_member_manager']);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                // Ensure name is unique *within this specific board*
                Rule::unique('columns')->where(function ($query) use ($board) {
                    return $query->where('board_id', $board->id);
                }),
            ],
        ]);

        try {
            $column = DB::transaction(function () use ($board, $validated) {
                $lockedBoard = Board::query()->lockForUpdate()->findOrFail($board->id);
                $maxPosition = $lockedBoard->columns()->max('position');
                $column = Column::create([
                    'name' => $validated['name'],
                    'board_id' => $lockedBoard->id,
                    'position' => $maxPosition === null ? 0 : $maxPosition + 1,
                ]);
                $lockedBoard->increment('layout_revision');

                return $column;
            });

            // Prepare data for frontend (you might want to return rendered HTML later)
            return response()->json([
                'success' => true,
                'message' => 'Cột đã được tạo thành công!',
                'column' => [
                    'id' => $column->id,
                    'name' => $column->name,
                    'position' => $column->position,
                    'revision' => $column->revision,
                    'url_update' => route('columns.update', ['board' => $board->id, 'column' => $column->id]),
                    'url_destroy' => route('columns.destroy', ['board' => $board->id, 'column' => $column->id]),
                ],
            ], 201);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Error creating column: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Không thể tạo cột. Đã xảy ra lỗi.'], 500);
        }
    }

    /**
     * Update the specified column (rename).
     */
    public function update(Request $request, Board $board, Column $column)
    {
        $this->authorizeBoardAccess($board, ['board_editor', 'board_member_manager']);

        // Ensure the column actually belongs to the board in the URL
        if ($column->board_id !== $board->id) {
            abort(404); // Or 403, column not found on this board
        }

        $validated = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'name' => [
                'required',
                'string',
                'max:255',
                // Ensure name is unique within this board, ignoring the current column
                Rule::unique('columns')->where(function ($query) use ($board) {
                    return $query->where('board_id', $board->id);
                })->ignore($column->id),
            ],
        ]);

        try {
            $updated = DB::transaction(function () use ($column, $validated) {
                $locked = Column::query()->lockForUpdate()->findOrFail($column->id);
                if ((int) $locked->revision !== (int) $validated['revision']) {
                    return false;
                }
                $locked->update(['name' => $validated['name']]);
                $locked->increment('revision');

                return $locked->fresh();
            });
            if (! $updated) {
                return response()->json([
                    'success' => false,
                    'code' => 'STALE_VERSION',
                    'message' => 'Cột đã được người khác cập nhật. Vui lòng tải lại.',
                ], 409);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tên cột đã được cập nhật.',
                'new_name' => $updated->name,
                'revision' => $updated->revision,
            ]);
        } catch (\Exception $e) {
            \Log::error("Error updating column {$column->id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Không thể cập nhật tên cột. Đã xảy ra lỗi.',
            ], 500);
        }
    }

    /**
     * Remove the specified column from storage.
     */
    public function destroy(Request $request, Board $board, Column $column)
    {
        $this->authorizeBoardAccess($board, ['board_member_manager']);

        if ($column->board_id !== $board->id) {
            abort(404);
        }
        $request->validate(['revision' => ['required', 'integer', 'min:1']]);

        // Tránh xoá nhầm: không cho xoá cột đang chứa công việc.
        if ($column->tasks()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xoá cột đang chứa công việc. Vui lòng di chuyển hoặc xoá các công việc trước.',
            ], 422);
        }

        try {
            $deleted = DB::transaction(function () use ($request, $column, $board) {
                $lockedBoard = Board::query()->lockForUpdate()->findOrFail($board->id);
                $locked = Column::query()->lockForUpdate()->findOrFail($column->id);
                if ((int) $locked->revision !== (int) $request->integer('revision')) {
                    return false;
                }
                $deletedPosition = $locked->position;
                $locked->delete();
                Column::where('board_id', $board->id)
                    ->where('position', '>', $deletedPosition)
                    ->decrement('position');
                $lockedBoard->increment('layout_revision');

                return true;
            });
            if (! $deleted) {
                return response()->json([
                    'success' => false,
                    'code' => 'STALE_VERSION',
                    'message' => 'Cột đã được người khác cập nhật. Vui lòng tải lại.',
                ], 409);
            }

            return response()->json(['success' => true, 'message' => 'Cột đã được xoá thành công.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Không thể xoá cột. Đã xảy ra lỗi.'], 500);
        }
    }

    public function reorder(Request $request, Board $board)
    {
        $this->authorizeBoardAccess($board, ['board_editor', 'board_member_manager']);

        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:columns,id',
            'layout_revision' => 'required|integer|min:1',
        ]);

        $orderedIds = array_map('intval', $request->input('order'));

        // Phải chứa đúng toàn bộ cột của bảng (không thiếu, không trùng),
        // nếu không việc đánh lại position sẽ tạo ra vị trí trùng/sai.
        $boardColumnIds = $board->columns()->pluck('id')->map(fn ($id) => (int) $id)->all();
        sort($boardColumnIds);
        $sortedInput = $orderedIds;
        sort($sortedInput);
        if ($sortedInput !== $boardColumnIds) {
            return response()->json([
                'success' => false,
                'message' => 'Danh sách sắp xếp phải chứa đúng toàn bộ cột của bảng.',
            ], 422);
        }

        try {
            $updated = DB::transaction(function () use ($board, $request, $orderedIds) {
                $lockedBoard = Board::query()->lockForUpdate()->findOrFail($board->id);
                if ((int) $lockedBoard->layout_revision !== (int) $request->integer('layout_revision')) {
                    return false;
                }
                $columns = Column::query()->where('board_id', $board->id)->lockForUpdate()->get()->keyBy('id');
                foreach ($orderedIds as $index => $columnId) {
                    $columns[$columnId]->update(['position' => $index]);
                }
                $lockedBoard->increment('layout_revision');

                return true;
            });
            if (! $updated) {
                return response()->json([
                    'success' => false,
                    'code' => 'STALE_LAYOUT',
                    'message' => 'Thứ tự cột đã được người khác thay đổi. Vui lòng tải lại.',
                ], 409);
            }

            return response()->json([
                'success' => true,
                'message' => 'Thứ tự cột đã được cập nhật.',
                'layout_revision' => $board->fresh()->layout_revision,
            ]);
        } catch (\Exception $e) {
            \Log::error("Error reordering columns for board {$board->id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Không thể cập nhật thứ tự cột. Đã xảy ra lỗi.',
            ], 500);
        }
    }
}
