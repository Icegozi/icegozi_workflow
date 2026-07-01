<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BoardTemplate;
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

        $templates = BoardTemplate::orderBy('position')->get()->map(fn ($t) => [
            'key' => $t->id,
            'name' => $t->name,
            'icon' => $t->icon,
            'description' => $t->description,
            'columns' => $t->columns,
        ]);

        return Inertia::render('User/Dashboard', [
            'boards' => $boards,
            'templates' => $templates,
        ]);
    }

    public function store()
    {
    }

    public function update()
    {
    }

    public function destroy()
    {
    }
}
