<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Column;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Khoá bất biến bảo mật: task_histories.note được render bằng v-html ở frontend
 * (TaskModal.vue, Tasks/Edit.vue, Boards/Show.vue) nên MỌI dữ liệu người dùng
 * nhúng vào note phải được escape ở server. Test này chống stored XSS regression.
 */
class TaskHistoryXssTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_lich_su_escape_du_lieu_nguoi_dung(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $board = Board::create(['name' => 'B', 'user_id' => $user->id]);
        $column = Column::create(['name' => 'C', 'position' => 0, 'board_id' => $board->id]);
        $task = Task::create(['title' => 'Cũ', 'column_id' => $column->id]);

        // Đổi tiêu đề sang payload XSS -> ghi lịch sử qua describeChange().
        $task->updateDetails(['title' => '<script>alert(1)</script>']);

        $note = $task->taskHistories()->latest()->first()->note;

        $this->assertStringNotContainsString('<script>', $note);
        $this->assertStringContainsString('&lt;script&gt;', $note);
    }
}
