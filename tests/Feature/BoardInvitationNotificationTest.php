<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Board;
use App\Models\Notification as DbNotification;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardInvitationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_registered_user_receives_and_accepts_board_invitation_from_in_app_notification(): void
    {
        $owner = User::factory()->create(['name' => 'Chủ bảng']);
        $invitee = User::factory()->create();
        $board = Board::create(['name' => 'Kế hoạch quý', 'user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('boards.invite', $board), [
                'email' => $invitee->email,
                'role_permission_name' => 'board_viewer',
            ])
            ->assertSessionHas('success');

        $notification = DbNotification::where('user_id', $invitee->id)->firstOrFail();

        $this->assertStringContainsString('Kế hoạch quý', $notification->message);
        $this->assertStringContainsString('/invitations/accept/', $notification->url);
        $this->assertStringContainsString('signature=', $notification->url);
        $this->actingAs($invitee)
            ->get($notification->url)
            ->assertRedirect(route('boards.show', $board));

        $this->assertTrue($invitee->hasBoardPermission($board, 'board_viewer'));

        // Mở lại cùng notification không được trả 404; đây là thao tác idempotent.
        $this->actingAs($invitee)
            ->get($notification->url)
            ->assertRedirect(route('boards.show', $board))
            ->assertSessionHas('info', 'Bạn đã là thành viên của bảng này.');
    }

    public function test_cannot_invite_email_without_registered_account(): void
    {
        $owner = User::factory()->create();
        $board = Board::create(['name' => 'Kế hoạch quý', 'user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('boards.invite', $board), [
                'email' => 'not-registered@example.com',
                'role_permission_name' => 'board_viewer',
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('board_invitations', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_mark_all_read_route_is_not_captured_by_notification_model_binding(): void
    {
        $user = User::factory()->create();
        DbNotification::notifyUser($user->id, 'Thông báo thứ nhất');
        DbNotification::notifyUser($user->id, 'Thông báo thứ hai');

        $this->actingAs($user)
            ->postJson(route('notifications.readAll'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(0, DbNotification::where('user_id', $user->id)->where('is_read', false)->count());
    }
}
