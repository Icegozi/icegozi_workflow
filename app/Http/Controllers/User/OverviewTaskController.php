<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Column;
use App\Models\Task;

class OverviewTaskController extends Controller
{
    private $board;

    public function __construct()
    {
        $this->board = new Board;
    }

    public function getTaskOverlayData($board_id)
    {
        if (! $board_id) {
            return response()->json(['success' => false, 'message' => 'board_id is required'], 400);
        }

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
