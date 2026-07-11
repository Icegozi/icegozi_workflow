<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Column;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bảo vệ job dọn file nội tuyến (media:prune-inline):
 *  - file còn được tham chiếu (mô tả task / bình luận) -> GIỮ, kể cả khi content là xoá-mềm gần đây;
 *  - file mồ côi -> xoá;
 *  - --dry-run không xoá gì.
 */
class InlineMediaPruneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('public');
    }

    private function makeTaskWithDescription(string $desc): Task
    {
        $owner = User::factory()->create();
        $board = Board::create(['name' => 'B', 'user_id' => $owner->id]);
        $column = Column::create(['name' => 'C', 'position' => 0, 'board_id' => $board->id]);

        return Task::create(['title' => 'T', 'description' => $desc, 'column_id' => $column->id]);
    }

    public function test_giu_file_duoc_tham_chieu_va_xoa_file_mo_coi(): void
    {
        Storage::disk('public')->put('editor/used.png', 'x');
        Storage::disk('public')->put('editor/orphan.png', 'y');

        // Task mô tả có nhúng used.png (URL tương đối /storage/...).
        $this->makeTaskWithDescription('Xem ảnh ![a](/storage/editor/used.png) nhé');

        $this->artisan('media:prune-inline', ['--days' => 0])->assertExitCode(0);

        Storage::disk('public')->assertExists('editor/used.png');    // còn tham chiếu -> giữ
        Storage::disk('public')->assertMissing('editor/orphan.png'); // mồ côi -> xoá
    }

    public function test_giu_file_duoc_binh_luan_xoa_mem_gan_day_tham_chieu(): void
    {
        Storage::disk('public')->put('editor/in-comment.png', 'x');
        $task = $this->makeTaskWithDescription('không có ảnh');
        $owner = User::factory()->create();
        $comment = Comment::create([
            'content' => 'ảnh ![a](/storage/editor/in-comment.png)',
            'user_id' => $owner->id,
            'task_id' => $task->id,
        ]);
        // Bình luận bị xoá mềm hôm nay -> vẫn trong retention 30 ngày -> ảnh phải được giữ.
        $comment->delete();

        $this->artisan('media:prune-inline', ['--days' => 30])->assertExitCode(0);

        Storage::disk('public')->assertExists('editor/in-comment.png');
    }

    public function test_dry_run_khong_xoa(): void
    {
        Storage::disk('public')->put('editor/orphan.png', 'y');

        $this->artisan('media:prune-inline', ['--days' => 0, '--dry-run' => true])->assertExitCode(0);

        Storage::disk('public')->assertExists('editor/orphan.png');
    }
}
