<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Board;
use App\Models\Column;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bảo vệ fix "gỡ người phụ trách rồi giao lại": trước đây 'assignees' dùng soft-delete,
 * mà quan hệ belongsToMany truy vấn thẳng pivot và bỏ qua scope deleted_at -> người đã gỡ
 * vẫn hiện lại và không giao lại được (dính 409). Bỏ soft-delete -> xoá cứng -> hết bug.
 */
class AssigneeReassignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->seed(PermissionSeeder::class);
    }

    /** Tạo owner + board + column + task. Owner là board member và có mọi quyền. */
    private function makeTask(): array
    {
        $owner = User::factory()->create();
        $board = Board::create(['name' => 'Dự án X', 'user_id' => $owner->id]);
        $column = Column::create(['name' => 'To do', 'position' => 0, 'board_id' => $board->id]);
        $task = Task::create(['title' => 'Việc cần làm', 'column_id' => $column->id]);

        return [$owner, $task];
    }

    public function test_go_nguoi_phu_trach_la_xoa_cung_va_giao_lai_duoc(): void
    {
        [$owner, $task] = $this->makeTask();

        // Giao
        $this->actingAs($owner)
            ->postJson(route('tasks.assignees.store', $task), ['user_id' => $owner->id])
            ->assertOk();
        $this->assertTrue($task->fresh()->assignees->contains($owner->id));

        // Gỡ -> xoá cứng, không còn dòng pivot (không "trashed" còn kẹt)
        $this->actingAs($owner)
            ->deleteJson(route('tasks.assignees.destroy', [$task, $owner->id]))
            ->assertOk();
        $this->assertFalse($task->fresh()->assignees->contains($owner->id));
        $this->assertDatabaseMissing('assignees', [
            'task_id' => $task->id,
            'user_id' => $owner->id,
        ]);

        // Giao lại: không bị 409, không "hiện lại" bản ghi cũ
        $this->actingAs($owner)
            ->postJson(route('tasks.assignees.store', $task), ['user_id' => $owner->id])
            ->assertOk();
        $this->assertTrue($task->fresh()->assignees->contains($owner->id));
    }

    public function test_cot_deleted_at_khong_con_tren_bang_assignees(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('assignees', 'deleted_at'),
            "Bảng 'assignees' là pivot, không được dùng soft-delete (deleted_at)."
        );
    }
}
