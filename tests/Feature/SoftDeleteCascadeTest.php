<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardPermission;
use App\Models\Column;
use App\Models\Comment;
use App\Models\Permission;
use App\Models\PermissionUser;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bảo vệ các sửa lỗi:
 *  - C1: xoá mềm board -> cascade xoá mềm column/task/con của task (không để lại dữ liệu mồ côi).
 *  - C2: board đã xoá mềm -> hasBoardPermission trả false dù pivot RBAC còn sót.
 *  - I4: user KHÔNG dùng soft-delete -> xoá là xoá cứng, giải phóng email/username để đăng ký lại.
 */
class SoftDeleteCascadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    /** Cấp một role cấp-bảng cho user qua chuỗi pivot. */
    private function grant(Board $board, User $user, string $permissionName): void
    {
        $permission = Permission::firstWhere('name', $permissionName);
        $pu = PermissionUser::firstOrCreate(['user_id' => $user->id, 'permission_id' => $permission->id]);
        BoardPermission::firstOrCreate(['board_id' => $board->id, 'permission_user_id' => $pu->id]);
    }

    public function test_c1_xoa_mem_board_cascade_xuong_column_task_va_comment(): void
    {
        $owner = User::factory()->create();
        $board = Board::create(['name' => 'Dự án X', 'user_id' => $owner->id]);
        $column = Column::create(['name' => 'To do', 'position' => 0, 'board_id' => $board->id]);
        $task = Task::create(['title' => 'Việc', 'column_id' => $column->id]);
        $comment = Comment::create(['content' => 'hi', 'user_id' => $owner->id, 'task_id' => $task->id]);

        $board->delete();

        // Bản ghi con biến mất khỏi truy vấn thường (đã xoá mềm)...
        $this->assertNull(Task::find($task->id));
        $this->assertNull(Column::find($column->id));
        $this->assertNull(Comment::find($comment->id));

        // ...nhưng vẫn còn dưới dạng trashed (khôi phục được nếu cần).
        $this->assertNotNull(Task::withTrashed()->find($task->id)->deleted_at);
        $this->assertNotNull(Column::withTrashed()->find($column->id)->deleted_at);
        $this->assertNotNull(Comment::withTrashed()->find($comment->id)->deleted_at);
    }

    public function test_c2_board_da_xoa_mem_khong_con_cap_quyen(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $board = Board::create(['name' => 'Dự án X', 'user_id' => $owner->id]);
        $this->grant($board, $viewer, 'board_viewer');

        $this->assertTrue($viewer->hasBoardPermission($board, 'board_viewer'));

        // Xoá mềm board: dùng lại instance đã trashed để mô phỏng luồng "load withTrashed".
        $board->delete();
        $trashed = Board::withTrashed()->find($board->id);

        // Dù pivot board_permissions còn sót, board đã xoá không cấp quyền cho bất kỳ ai...
        $this->assertFalse($viewer->hasBoardPermission($trashed, 'board_viewer'));
        // ...kể cả owner.
        $this->assertFalse($owner->hasBoardPermission($trashed, 'board_viewer'));
    }

    public function test_i4_xoa_user_la_xoa_cung_giai_phong_email(): void
    {
        $user = User::factory()->create(['email' => 'reuse@example.com', 'username' => 'reuse']);

        User::deleteUserById($user->id);

        // Xoá cứng: không còn bản ghi (kể cả trashed) -> email/username dùng lại được.
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertSame(0, User::where('email', 'reuse@example.com')->count());
    }
}
