<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Assignee;
use App\Models\Board;
use App\Models\BoardPermission;
use App\Models\Column;
use App\Models\Permission;
use App\Models\PermissionUser;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveBoardWithHandoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->seed(PermissionSeeder::class);
    }

    private function grant(Board $board, User $user, string $permissionName): void
    {
        $permission = Permission::firstWhere('name', $permissionName);
        $permissionUser = PermissionUser::firstOrCreate([
            'user_id' => $user->id,
            'permission_id' => $permission->id,
        ]);

        BoardPermission::firstOrCreate([
            'board_id' => $board->id,
            'permission_user_id' => $permissionUser->id,
        ]);
    }

    private function createBoardWithTask(User $owner): array
    {
        $board = Board::create(['name' => 'Dự án bàn giao', 'user_id' => $owner->id]);
        $column = Column::create(['name' => 'Cần làm', 'position' => 0, 'board_id' => $board->id]);
        $task = Task::create(['title' => 'Task cần bàn giao', 'column_id' => $column->id]);

        return [$board, $task];
    }

    public function test_member_must_handover_assigned_tasks_before_leaving_board(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        [$board, $task] = $this->createBoardWithTask($owner);
        $this->grant($board, $viewer, 'board_viewer');
        Assignee::create(['task_id' => $task->id, 'user_id' => $viewer->id]);

        $this->actingAs($viewer)
            ->postJson(route('boards.leave', $board), [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Hãy bàn giao toàn bộ công việc trước khi rời bảng.');

        $this->assertTrue($viewer->fresh()->hasBoardPermission($board, 'board_viewer'));
        $this->assertDatabaseHas('assignees', ['task_id' => $task->id, 'user_id' => $viewer->id]);
    }

    public function test_member_can_leave_after_all_assigned_tasks_are_handed_over(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        [$board, $task] = $this->createBoardWithTask($owner);
        $this->grant($board, $viewer, 'board_viewer');
        Assignee::create(['task_id' => $task->id, 'user_id' => $viewer->id]);

        Assignee::where('task_id', $task->id)->where('user_id', $viewer->id)->delete();
        Assignee::create(['task_id' => $task->id, 'user_id' => $owner->id]);

        $this->actingAs($viewer)
            ->postJson(route('boards.leave', $board))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertFalse($viewer->fresh()->hasBoardPermission($board, 'board_viewer'));
        $this->assertDatabaseHas('assignees', ['task_id' => $task->id, 'user_id' => $owner->id]);
    }

    public function test_member_with_assigned_task_cannot_leave_even_if_an_outsider_is_provided(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        [$board, $task] = $this->createBoardWithTask($owner);
        $this->grant($board, $viewer, 'board_viewer');
        Assignee::create(['task_id' => $task->id, 'user_id' => $viewer->id]);

        $this->actingAs($viewer)
            ->postJson(route('boards.leave', $board), ['handover_user_id' => $outsider->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Hãy bàn giao toàn bộ công việc trước khi rời bảng.');

        $this->assertTrue($viewer->fresh()->hasBoardPermission($board, 'board_viewer'));
        $this->assertDatabaseHas('assignees', ['task_id' => $task->id, 'user_id' => $viewer->id]);
    }
}
