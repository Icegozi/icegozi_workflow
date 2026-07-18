<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Board;
use App\Models\Checklist;
use App\Models\Column;
use App\Models\Label;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollaborationConflictTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->seed(PermissionSeeder::class);
    }

    private function makeBoard(): array
    {
        $owner = User::factory()->create();
        $board = Board::create(['name' => 'Dự án cộng tác', 'user_id' => $owner->id]);
        $first = Column::create(['name' => 'To do', 'position' => 0, 'board_id' => $board->id]);
        $second = Column::create(['name' => 'Doing', 'position' => 1, 'board_id' => $board->id]);
        $task = Task::create(['title' => 'Việc A', 'column_id' => $first->id]);

        return [$owner, $board, $first, $second, $task];
    }

    public function test_stale_task_update_is_rejected_without_losing_newer_content(): void
    {
        [$owner, , , , $task] = $this->makeBoard();

        $this->actingAs($owner)
            ->putJson(route('tasks.update', $task), ['title' => 'Bản mới', 'revision' => 1])
            ->assertOk()
            ->assertJsonPath('task.revision', 2);

        $this->actingAs($owner)
            ->putJson(route('tasks.update', $task), ['title' => 'Bản cũ', 'revision' => 1])
            ->assertStatus(409)
            ->assertJsonPath('code', 'STALE_VERSION')
            ->assertJsonPath('current.title', 'Bản mới');

        $this->assertSame('Bản mới', $task->fresh()->title);
    }

    public function test_stale_task_delete_is_rejected(): void
    {
        [$owner, , , , $task] = $this->makeBoard();
        $task->increment('revision');

        $this->actingAs($owner)
            ->deleteJson(route('tasks.destroy', $task), ['revision' => 1])
            ->assertStatus(409)
            ->assertJsonPath('code', 'STALE_VERSION');

        $this->assertNotNull(Task::find($task->id));
    }

    public function test_stale_column_layout_is_rejected_after_a_newer_move(): void
    {
        [$owner, , $source, , $task] = $this->makeBoard();
        $secondTask = Task::create(['title' => 'Việc B', 'column_id' => $source->id]);

        $this->actingAs($owner)
            ->postJson(route('tasks.updatePosition'), [
                'task_id' => $task->id,
                'new_column_id' => $source->id,
                'order' => [$secondTask->id, $task->id],
                'source_column_revision' => 1,
                'target_column_revision' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('source_column_revision', 2);

        $this->actingAs($owner)
            ->postJson(route('tasks.updatePosition'), [
                'task_id' => $task->id,
                'new_column_id' => $source->id,
                'order' => [$task->id, $secondTask->id],
                'source_column_revision' => 1,
                'target_column_revision' => 1,
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'STALE_LAYOUT');
    }

    public function test_stale_label_delete_is_rejected(): void
    {
        [$owner, $board] = $this->makeBoard();
        $label = Label::create(['board_id' => $board->id, 'name' => 'Gấp', 'color' => '#e5484d']);
        $label->increment('revision');

        $this->actingAs($owner)
            ->deleteJson(route('labels.destroy', $label), ['revision' => 1])
            ->assertStatus(409)
            ->assertJsonPath('code', 'STALE_VERSION');

        $this->assertNotNull(Label::find($label->id));
    }

    public function test_stale_empty_column_delete_is_rejected(): void
    {
        [$owner, $board] = $this->makeBoard();
        $column = Column::create(['name' => 'Trống', 'position' => 2, 'board_id' => $board->id]);
        $column->increment('revision');

        $this->actingAs($owner)
            ->deleteJson(route('columns.destroy', [$board, $column]), ['revision' => 1])
            ->assertStatus(409)
            ->assertJsonPath('code', 'STALE_VERSION');

        $this->assertNotNull(Column::find($column->id));
    }

    public function test_checklist_change_invalidates_a_stale_task_edit(): void
    {
        [$owner, , , , $task] = $this->makeBoard();

        $this->actingAs($owner)
            ->postJson(route('checklists.store', $task), ['title' => 'Kiểm tra'])
            ->assertCreated();

        $this->assertSame(2, $task->fresh()->revision);
        $this->actingAs($owner)
            ->putJson(route('tasks.update', $task), ['title' => 'Bản cũ', 'revision' => 1])
            ->assertStatus(409)
            ->assertJsonPath('code', 'STALE_VERSION');
    }

    public function test_stale_column_reorder_is_rejected(): void
    {
        [$owner, $board, $first, $second] = $this->makeBoard();

        $this->actingAs($owner)
            ->postJson(route('columns.reorder', $board), [
                'order' => [$second->id, $first->id],
                'layout_revision' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('layout_revision', 2);

        $this->actingAs($owner)
            ->postJson(route('columns.reorder', $board), [
                'order' => [$first->id, $second->id],
                'layout_revision' => 1,
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'STALE_LAYOUT');
    }

    public function test_stale_checklist_update_is_rejected_without_overwriting_newer_change(): void
    {
        [$owner, , , , $task] = $this->makeBoard();
        $checklist = Checklist::create(['task_id' => $task->id, 'title' => 'Bản đầu', 'position' => 0]);

        $this->actingAs($owner)
            ->putJson(route('checklists.update', $checklist), ['title' => 'Bản mới', 'revision' => 1])
            ->assertOk()
            ->assertJsonPath('checklist.revision', 2);

        $this->actingAs($owner)
            ->putJson(route('checklists.update', $checklist), ['title' => 'Bản cũ', 'revision' => 1])
            ->assertStatus(409)
            ->assertJsonPath('code', 'STALE_VERSION')
            ->assertJsonPath('current.title', 'Bản mới');
    }

    public function test_stale_board_rename_is_rejected(): void
    {
        [$owner, $board] = $this->makeBoard();

        $this->actingAs($owner)
            ->putJson(route('boards.update', $board), ['name' => 'Bảng mới', 'revision' => 1])
            ->assertOk();

        $this->actingAs($owner)
            ->putJson(route('boards.update', $board), ['name' => 'Bảng cũ', 'revision' => 1])
            ->assertStatus(409)
            ->assertJsonPath('code', 'STALE_VERSION');

        $this->assertSame('Bảng mới', $board->fresh()->name);
    }
}
