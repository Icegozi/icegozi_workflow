<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Column;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;

class OverviewTaskController extends Controller
{
    private $board;

    public function __construct()
    {
        $this->board = new Board();
    }

    public function getTaskOverlayData($board_id)
    {
        if (! $board_id) {
            return response()->json(['success' => false, 'message' => 'board_id is required'], 400);
        }

        $board = Board::findOrFail($board_id);
        $user = Auth::user();
        $canView = $user->hasBoardPermission($board, 'board_viewer')
            || $user->hasBoardPermission($board, 'board_editor')
            || $user->hasBoardPermission($board, 'board_member_manager');
        abort_unless($canView, 403, 'Bạn không có quyền xem bảng này.');

        $assignees = $this->board->getAssignedUsersByBoardId($board_id);

        $board_name = $this->board->getBoardNameById($board_id);

        $totalColumns = Column::where('board_id', $board_id)->count();

        $totalTasks = Task::whereHas('column', function ($query) use ($board_id) {
            $query->where('board_id', $board_id);
        })->count();

        return response()->json([
            'success' => true,
            'assignees' => $assignees,
            'board_name' => $board_name,
            'total_columns' => $totalColumns,
            'total_tasks' => $totalTasks,
        ]);
    }
}
