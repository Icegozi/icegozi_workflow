<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Board;
use App\Models\Column;
use App\Models\Notification as DbNotification;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAccessRequestNotification;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->seed(PermissionSeeder::class);
    }

    /** Tạo owner + board + column + task, trả về [owner, task, board]. */
    private function makeTask(): array
    {
        $owner = User::factory()->create();
        $board = Board::create(['name' => 'Dự án X', 'user_id' => $owner->id]);
        $column = Column::create(['name' => 'To do', 'position' => 0, 'board_id' => $board->id]);
        $task = Task::create(['title' => 'Việc cần làm', 'column_id' => $column->id]);

        return [$owner, $task, $board];
    }

    /** URL đẹp của task. */
    private function permalinkUrl($board, $task): string
    {
        return route('tasks.permalink', ['boardCode' => $board->board_code, 'taskCode' => $task->task_code]);
    }

    public function test_owner_mo_permalink_thay_trang_chi_tiet(): void
    {
        [$owner, $task, $board] = $this->makeTask();

        $this->actingAs($owner)
            ->get($this->permalinkUrl($board, $task))
            ->assertInertia(fn (Assert $page) => $page->component('Tasks/View')->where('taskId', $task->id));
    }

    public function test_link_cu_tasks_id_redirect_sang_url_dep(): void
    {
        [$owner, $task, $board] = $this->makeTask();

        $this->actingAs($owner)
            ->get(route('tasks.show', $task->id))
            ->assertRedirect($this->permalinkUrl($board, $task));
    }

    public function test_ma_task_tang_dan_theo_tung_board(): void
    {
        [, $task, $board] = $this->makeTask();
        // Task đầu tiên của board -> task_code = 1.
        $this->assertSame(1, (int) $task->task_code);
        $this->assertNotNull($board->board_code);
    }

    public function test_i2_task_gan_board_id_va_unique_chan_trung_ma(): void
    {
        [, $task, $board] = $this->makeTask();
        // board_id được ghi để ràng buộc unique(board_id, task_code).
        $this->assertSame((int) $board->id, (int) $task->board_id);

        // Task thứ 2 cùng board -> mã kế tiếp, không trùng.
        $task2 = Task::create(['title' => 'Việc 2', 'column_id' => $task->column_id]);
        $this->assertSame(2, (int) $task2->task_code);
        $this->assertSame((int) $board->id, (int) $task2->board_id);

        // Ép trùng (board_id, task_code) -> DB chặn (lưới an toàn khi lock không đủ).
        $this->expectException(QueryException::class);
        DB::table('tasks')->where('id', $task2->id)->update(['task_code' => 1]);
    }

    public function test_permalink_xhr_json_van_tra_json(): void
    {
        [$owner, $task] = $this->makeTask();

        $this->actingAs($owner)
            ->getJson(route('tasks.show', $task->id))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_permalink_task_khong_ton_tai_chuyen_ve_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('tasks.show', 999999))
            ->assertRedirect(route('dashboard'));
    }

    public function test_xhr_json_task_khong_ton_tai_tra_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('tasks.show', 999999))
            ->assertNotFound();
    }

    public function test_nguoi_khong_quyen_bi_chuyen_toi_form_xin_quyen(): void
    {
        [, $task, $board] = $this->makeTask();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get($this->permalinkUrl($board, $task))
            ->assertRedirect(route('tasks.request-access', $task->id));
    }

    public function test_gui_yeu_cau_tao_notification_va_gui_mail(): void
    {
        Notification::fake();
        [$owner, $task] = $this->makeTask();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('tasks.request-access.submit', $task->id), ['note' => 'Cho mình xem với'])
            ->assertRedirect(route('tasks.request-access', $task->id))
            ->assertSessionHas('success');

        // In-app notification (App\Models\Notification) cho owner.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'task_id' => $task->id,
        ]);
        $notif = DbNotification::where('user_id', $owner->id)->first();
        $this->assertStringContainsString('grant', $notif->url); // link cấp quyền đã ký

        // Email tới owner.
        Notification::assertSentTo($owner, TaskAccessRequestNotification::class);
    }

    public function test_nguoi_da_co_quyen_khong_can_xin(): void
    {
        [$owner, $task] = $this->makeTask();

        // Owner mở form xin quyền -> vào thẳng công việc.
        $this->actingAs($owner)
            ->get(route('tasks.request-access', $task->id))
            ->assertRedirect(route('tasks.show', $task->id));
    }

    public function test_owner_cap_quyen_qua_signed_url(): void
    {
        [$owner, $task, $board] = $this->makeTask();
        $requester = User::factory()->create();

        $grantUrl = URL::temporarySignedRoute('tasks.grant', now()->addDays(7), [
            'task' => $task->id,
            'requester' => $requester->id,
        ]);

        $this->actingAs($owner)->get($grantUrl)
            ->assertRedirect(route('boards.show', $task->column->board_id));

        // Người xin giờ đã xem được công việc (không còn bị chuyển hướng xin quyền).
        $this->actingAs($requester)
            ->get($this->permalinkUrl($board, $task))
            ->assertInertia(fn (Assert $page) => $page->component('Tasks/View'));

        // Requester nhận được notification "đã được cấp quyền".
        $this->assertDatabaseHas('notifications', ['user_id' => $requester->id, 'task_id' => $task->id]);
    }

    public function test_nguoi_khong_phai_owner_khong_the_cap_quyen(): void
    {
        [, $task] = $this->makeTask();
        $requester = User::factory()->create();
        $stranger = User::factory()->create();

        $grantUrl = URL::temporarySignedRoute('tasks.grant', now()->addDays(7), [
            'task' => $task->id,
            'requester' => $requester->id,
        ]);

        $this->actingAs($stranger)->get($grantUrl)->assertForbidden();
    }

    public function test_grant_khong_co_chu_ky_bi_chan(): void
    {
        [$owner, $task] = $this->makeTask();
        $requester = User::factory()->create();

        // Truy cập route grant không có chữ ký hợp lệ -> 403.
        $this->actingAs($owner)
            ->get(route('tasks.grant', ['task' => $task->id, 'requester' => $requester->id]))
            ->assertForbidden();
    }
}
