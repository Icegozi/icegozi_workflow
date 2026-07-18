<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Board;
use App\Models\Column;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    private function makeTask(): array
    {
        $owner = User::factory()->create(['avatar_url' => null]);
        $board = Board::create(['name' => 'Dự án comment', 'user_id' => $owner->id]);
        $column = Column::create(['name' => 'To do', 'position' => 0, 'board_id' => $board->id]);
        $task = Task::create(['title' => 'Việc cần trao đổi', 'column_id' => $column->id]);

        return [$owner, $task];
    }

    public function test_them_binh_luan_trim_noi_dung_va_tra_avatar_mac_dinh(): void
    {
        [$owner, $task] = $this->makeTask();

        $this->actingAs($owner)
            ->postJson(route('comments.store', $task), ['content' => '  Cần kiểm tra phần này.  '])
            ->assertCreated()
            ->assertJsonPath('comment.content', 'Cần kiểm tra phần này.')
            ->assertJsonPath('comment.user_avatar', '/images/default-avatar.svg')
            ->assertJsonPath('comment.user_avatar_url', '/images/default-avatar.svg')
            ->assertJsonPath('comment.can_delete', true);
    }

    public function test_khong_the_them_binh_luan_chi_co_khoang_trang(): void
    {
        [$owner, $task] = $this->makeTask();

        $this->actingAs($owner)
            ->postJson(route('comments.store', $task), ['content' => " \n\t "])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_xoa_binh_luan_khong_ton_tai_tra_404_thay_vi_500(): void
    {
        [$owner, $task] = $this->makeTask();

        $this->actingAs($owner)
            ->deleteJson(route('comments.destroy', [$task, 999999]))
            ->assertNotFound();
    }

    public function test_accessor_avatar_cua_comment_luon_co_fallback_noi_bo(): void
    {
        [$owner, $task] = $this->makeTask();
        $comment = Comment::create([
            'task_id' => $task->id,
            'user_id' => $owner->id,
            'content' => 'Bình luận có avatar mặc định.',
        ]);

        $this->assertSame('/images/default-avatar.svg', $comment->user_avatar_url);
    }
}
