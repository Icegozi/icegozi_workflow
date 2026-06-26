<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\BoardRequest;
use App\Models\Board;
use Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $boards = $user->getAllAccessibleBoards()->map(function ($board) use ($user) {
            return [
                'id' => $board->id,
                'name' => $board->name,
                'currentUserRole' => $user->getRoleForBoard($board),
                'updated_at' => optional($board->updated_at)->format('d/m/Y'),
                'show_url' => route('boards.show', $board->id),
            ];
        })->values();

        return Inertia::render('User/Dashboard', ['boards' => $boards]);
    }

    public function store(BoardRequest $request)
    {
    }

    public function update(BoardRequest $request, Board $board)
    {
    }

    public function destroy(Board $board)
    {
    }
}
