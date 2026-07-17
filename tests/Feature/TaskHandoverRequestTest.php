<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Assignee;
use App\Models\Board;
use App\Models\BoardPermission;
use App\Models\Column;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\PermissionUser;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskHandoverRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->seed(PermissionSeeder::class);
    }

    private function grant(Board $board, User $user): void
    {
        $permission = Permission::firstWhere('name', 'board_viewer');
        $permissionUser = PermissionUser::firstOrCreate([
            'user_id' => $user->id,
            'permission_id' => $permission->id,
        ]);
        BoardPermission::firstOrCreate([
            'board_id' => $board->id,
            'permission_user_id' => $permissionUser->id,
        ]);
    }

    private function fixture(): array
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $board = Board::create(['name' => 'Bàn giao', 'user_id' => $owner->id]);
        $column = Column::create(['name' => 'Cần làm', 'position' => 0, 'board_id' => $board->id]);
        $task = Task::create(['title' => 'Task', 'column_id' => $column->id]);
        $this->grant($board, $viewer);
        Assignee::create(['task_id' => $task->id, 'user_id' => $viewer->id]);

        return [$owner, $viewer, $board, $task];
    }

    public function test_viewer_request_is_accepted_once_and_transfers_assignment(): void
    {
        [$owner, $viewer, $board, $task] = $this->fixture();

        $this->actingAs($viewer)
            ->postJson(route('tasks.handover-requests.store', $task), ['to_user_id' => $owner->id])
            ->assertOk();
        $this->actingAs($viewer)
            ->postJson(route('tasks.handover-requests.store', $task), ['to_user_id' => $owner->id])
            ->assertOk();

        $this->assertDatabaseCount('task_handover_requests', 1);
        $this->assertSame(1, Notification::where('user_id', $owner->id)->count());
        $requestId = (int) DB::table('task_handover_requests')->value('id');

        $this->actingAs($owner)
            ->postJson(route('task-handover-requests.accept', $requestId))
            ->assertOk();

        $this->assertDatabaseMissing('assignees', ['task_id' => $task->id, 'user_id' => $viewer->id]);
        $this->assertDatabaseHas('assignees', ['task_id' => $task->id, 'user_id' => $owner->id]);
        $this->actingAs($owner)
            ->postJson(route('task-handover-requests.accept', $requestId))
            ->assertConflict();
    }

    public function test_accept_rejects_stale_request_when_sender_is_no_longer_assigned(): void
    {
        [$owner, $viewer, $board, $task] = $this->fixture();
        $this->actingAs($viewer)
            ->postJson(route('tasks.handover-requests.store', $task), ['to_user_id' => $owner->id])
            ->assertOk();
        Assignee::where('task_id', $task->id)->where('user_id', $viewer->id)->delete();
        $requestId = (int) DB::table('task_handover_requests')->value('id');

        $this->actingAs($owner)
            ->postJson(route('task-handover-requests.accept', $requestId))
            ->assertConflict();
    }
}
