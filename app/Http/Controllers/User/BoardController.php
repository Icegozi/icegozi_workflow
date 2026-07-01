<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\BoardRequest;
use App\Models\Board;
use App\Models\Column;
use Auth;

class BoardController extends Controller
{
    private function authorizeBoardAccess(Board $board, array $requiredPermissions = [])
    {
        $user = Auth::user();
        // Kiểm tra nếu người dùng có một trong các quyền yêu cầu
        foreach ($requiredPermissions as $permission) {
            if ($user->hasBoardPermission($board, $permission)) {
                return $board;
            }
        }

        abort(403, 'Bạn không có quyền thực hiện thao tác!');
    }

    public function store(BoardRequest $request)
    {
        $validated = $request->validated();
        $column = new Column();
        $boards = new Board();

        $data = [
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ];
        $board = $boards->createBoard($data);
        $column->createDefaultColumns($board->id);

        return redirect()->route('user.dashboard')->with('success', 'Bảng đã được tạo thành công!');
    }

    public function show(Board $board)
    {
        $this->authorizeBoardAccess($board, ['board_viewer', 'board_editor', 'board_member_manager']);
        $board->load([
            'columns' => function ($query) {
                $query->orderBy('position', 'asc');
            },
            'columns.tasks' => function ($query) {
                $query->with('assignees')
                    ->withCount([
                        'comments',
                        'attachments',
                        'checklists',
                        'checklists as checklists_done_count' => fn ($q) => $q->where('is_done', true),
                    ])
                    ->orderBy('position', 'asc');
            },
        ]);

        $user = Auth::user();
        $canEdit = $user->hasBoardPermission($board, 'board_editor')
            || $user->hasBoardPermission($board, 'board_member_manager');
        $canManage = $user->hasBoardPermission($board, 'board_member_manager');

        return \Inertia\Inertia::render('Boards/Show', [
            'board' => [
                'id' => $board->id,
                'name' => $board->name,
                'columns' => $board->columns->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'position' => $c->position,
                    'tasks' => $c->tasks->map(fn ($t) => [
                        'id' => $t->id,
                        'title' => $t->title,
                        'column_id' => $t->column_id,
                        'position' => $t->position,
                        'priority' => $t->priority,
                        'has_description' => filled($t->description),
                        'due_date' => $t->due_date ? $t->due_date->toDateString() : null,
                        'formatted_due_date' => $t->due_date ? $t->due_date->format('d/m/Y') : null,
                        'comments_count' => $t->comments_count,
                        'attachments_count' => $t->attachments_count,
                        'checklist_total' => $t->checklists_count,
                        'checklist_done' => $t->checklists_done_count,
                        'assignees' => $t->assignees->map(fn ($u) => [
                            'id' => $u->id,
                            'name' => $u->name,
                            'email' => $u->email,
                        ])->values(),
                    ])->values(),
                ])->values(),
            ],
            'canEdit' => $canEdit,
            'canManage' => $canManage,
        ]);
    }

    public function update(BoardRequest $request, Board $board)
    {
        $this->authorizeBoardAccess($board, ['board_editor', 'board_member_manager']);

        $validated = $request->validated();

        $board->update(['name' => $validated['name']]);

        return redirect()->back()->with('success', 'Tên bảng đã được cập nhật.');
    }

    public function destroy(Board $board)
    {
        $this->authorizeBoardAccess($board, ['board_member_manager']);

        $board->delete();

        return redirect()->route('user.dashboard')->with('success', 'Bảng đã được xoá thành công.');
    }
}
