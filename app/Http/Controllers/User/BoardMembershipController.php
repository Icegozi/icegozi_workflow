<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\BoardInvitation;
use App\Models\BoardPermission;
use App\Models\Notification as InAppNotification;
use App\Models\Permission;
use App\Models\PermissionUser;
use App\Models\Task;
use App\Models\TaskHandoverRequest;
use App\Models\User;
use Auth;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Str;

class BoardMembershipController extends Controller
{
    // Helper function to grant a permission to a user for a board
    private function grantBoardPermission(Board $board, User $user, string $permissionName): bool
    {
        $permission = Permission::firstWhere('name', $permissionName);
        if (! $permission) {
            \Log::error("Permission {$permissionName} not found.");

            return false;
        }

        // Find or create the permission_users link
        $permissionUser = PermissionUser::firstOrCreate(
            ['user_id' => $user->id, 'permission_id' => $permission->id]
        );

        // Link this permission_user to the board
        BoardPermission::firstOrCreate(
            ['board_id' => $board->id, 'permission_user_id' => $permissionUser->id]
        );

        return true;
    }

    // Helper to revoke ALL board-specific permissions for a user on a board
    private function revokeAllBoardPermissionsForUser(Board $board, User $user)
    {
        $permissionUserIds = PermissionUser::where('user_id', $user->id)->pluck('id');
        BoardPermission::where('board_id', $board->id)
            ->whereIn('permission_user_id', $permissionUserIds)
            ->delete();
    }

    private function cancelPendingHandoverRequests(Board $board, User $user): void
    {
        TaskHandoverRequest::where('status', 'pending')
            ->where(fn ($query) => $query->where('from_user_id', $user->id)->orWhere('to_user_id', $user->id))
            ->whereHas('task.column', fn ($query) => $query->where('board_id', $board->id))
            ->update(['status' => 'cancelled']);
    }

    public function settings(Board $board) // Route model binding for $board
    {
        // Authorization: Only board owner or those with 'board_member_manager' permission
        if (Auth::id() !== $board->user_id && ! Auth::user()->hasBoardPermission($board, 'board_member_manager')) {
            abort(403, 'Bạn không có quyền truy cập cài đặt của bảng này.');
        }

        // Eager load necessary relationships for the view
        // The 'owner' is already available if $board->user_id is the foreign key
        // $board->load(['owner']); // Already available as $board->owner if relation is set up

        $membersData = $board->getMembersWithRoles();
        $pendingInvitations = $board->invitations()->with('inviter')->whereNull('accepted_at')->get();

        $potentialRoles = [
            'board_viewer' => 'Người xem',
            'board_editor' => 'Người chỉnh sửa',
            'board_member_manager' => 'Người quản lý',
        ];

        $members = collect($membersData)->map(function ($item) use ($potentialRoles) {
            $u = $item['user'];
            $highest = null;
            foreach (array_keys($potentialRoles) as $roleKey) {
                if (in_array($roleKey, $item['roles'])) {
                    $highest = $roleKey;
                    break;
                }
            }

            return ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $highest];
        })->values();

        $invitations = $pendingInvitations->map(fn ($inv) => [
            'id' => $inv->id,
            'email' => $inv->email,
            'role_permission_name' => $inv->role_permission_name,
            'inviter_name' => optional($inv->inviter)->name,
            'created_at_human' => optional($inv->created_at)->diffForHumans(),
        ])->values();

        $canManage = Auth::id() === $board->user_id
            || Auth::user()->hasBoardPermission($board, 'board_member_manager');

        return Inertia::render('Boards/Settings', [
            'board' => ['id' => $board->id, 'name' => $board->name],
            'owner' => ['name' => $board->owner->name, 'email' => $board->owner->email],
            'members' => $members,
            'invitations' => $invitations,
            'potentialRoles' => $potentialRoles,
            'canManage' => $canManage,
        ]);
    }

    public function inviteMember(Request $request, Board $board)
    {
        if (Auth::id() !== $board->user_id && ! Auth::user()->hasBoardPermission($board, 'board_member_manager')) {
            abort(403, 'Bạn không có quyền mời thành viên.');
        }

        $request->validate([
            'email' => 'required|email:rfc,strict|exists:users,email',
            // Ensure role is a valid permission name
            'role_permission_name' => 'required|string|exists:permissions,name',
        ], [
            'email.exists' => 'Email này chưa có tài khoản trong hệ thống.',
        ]);

        $emailToInvite = $request->input('email');
        $permissionNameToGrant = $request->input('role_permission_name');

        $invitedUser = User::firstWhere('email', $emailToInvite);
        if (! $invitedUser) {
            return back()->withErrors(['email' => 'Email này chưa có tài khoản trong hệ thống.'])->withInput();
        }

        $existingRoleError = $this->checkInviteeAlreadyHasRole($board, $invitedUser, $permissionNameToGrant);
        if ($existingRoleError) {
            return $existingRoleError;
        }

        // --- Using BoardInvitations table (Recommended) ---
        $existingInvitation = BoardInvitation::where('board_id', $board->id)
            ->where('email', $emailToInvite)->whereNull('accepted_at')->first();
        if ($existingInvitation) {
            return back()->with('warning', 'Đã có lời mời đang chờ xử lý cho email này.');
        }

        try {
            DB::transaction(function () use ($board, $emailToInvite, $permissionNameToGrant, $invitedUser) {
                $invitation = $this->createBoardInvitation($board, $emailToInvite, $permissionNameToGrant);
                $this->sendInAppInvitation($invitation, $invitedUser);
            });
        } catch (\Throwable $e) {
            \Log::error('Failed to create board invitation notification: ' . $e->getMessage());

            return back()->with('error', 'Không thể gửi thông báo mời. Vui lòng thử lại.');
        }

        return back()->with('success', 'Lời mời đã được gửi qua thông báo tới ' . $emailToInvite);
    }

    // Trả về redirect lỗi nếu người được mời là chủ sở hữu hoặc đã có vai trò tương đương/cao hơn; ngược lại null.
    private function checkInviteeAlreadyHasRole(Board $board, User $invitedUser, string $permissionNameToGrant)
    {
        if ($invitedUser->id === $board->user_id) {
            return back()->with('error', 'Người dùng này đã là chủ sở hữu của bảng.');
        }
        if ($invitedUser->hasBoardPermission($board, $permissionNameToGrant)) {
            return back()->with('error', 'Người dùng này đã có vai trò tương tự hoặc cao hơn trong bảng.');
        }

        return null;
    }

    // Tạo bản ghi lời mời với token duy nhất.
    private function createBoardInvitation(
        Board $board,
        string $emailToInvite,
        string $permissionNameToGrant
    ): BoardInvitation {
        do {
            $token = Str::random(40);
        } while (BoardInvitation::where('token', $token)->exists());

        return BoardInvitation::create([
            'board_id' => $board->id,
            'email' => $emailToInvite,
            'token' => $token,
            'role_permission_name' => $permissionNameToGrant,
            'invited_by' => Auth::id(),
            'expires_at' => now()->addDays(7),
        ]);
    }

    private function sendInAppInvitation(BoardInvitation $invitation, User $invitedUser): void
    {
        $invitation->loadMissing(['board', 'inviter']);
        $acceptUrl = URL::temporarySignedRoute(
            'invitations.accept',
            $invitation->expires_at ?? now()->addDays(7),
            ['token' => $invitation->token]
        );
        $message = '<strong>' . e($invitation->inviter->name) . '</strong> đã mời bạn tham gia bảng '
            . '<strong>' . e($invitation->board->name) . '</strong>.';

        InAppNotification::notifyUser($invitedUser->id, $message, $acceptUrl);
    }

    public function acceptInvitation(Request $request, $token)
    {
        if (! $request->hasValidSignature()) {
            abort(401, 'Liên kết mời không hợp lệ hoặc đã hết hạn.');
        }

        $invitation = $this->resolveValidInvitation($token);

        $notLoggedIn = $this->ensureAuthenticatedInvitee($invitation);
        if ($notLoggedIn) {
            return $notLoggedIn;
        }

        // Signed URL có thể được mở lại từ notification. Lời mời đã
        // chấp nhận là trạng thái thành công, không phải tài nguyên không tồn tại.
        if ($invitation->accepted_at) {
            return redirect()->route('boards.show', $invitation->board_id)
                ->with('info', 'Bạn đã là thành viên của bảng này.');
        }

        $user = Auth::user();

        // Người mời phải còn quyền quản lý tại thời điểm chấp nhận, tránh tình huống
        // một quản trị viên đã bị gỡ/giáng cấp nhưng lời mời cũ vẫn cấp quyền cao.
        $board = $invitation->board;
        $this->ensureInviterStillAuthorized($invitation, $board);

        DB::transaction(function () use ($invitation, $board, $user) {
            if (! $this->grantBoardPermission($board, $user, $invitation->role_permission_name)) {
                // This scenario should be rare if permission exists, but good to handle
                throw new \Exception("Không thể cấp quyền '{$invitation->role_permission_name}'.");
            }
            $invitation->update(['accepted_at' => now()]);
        });

        return redirect()->route('boards.show', $invitation->board_id)
            ->with('success', 'Bạn đã tham gia thành công vào bảng ' . $invitation->board->name);
    }

    // Lấy lời mời theo token. Lời mời đã chấp nhận vẫn được trả về để lần mở
    // link tiếp theo chuyển tới board; chỉ lời mời chưa chấp nhận mới xét hết hạn.
    private function resolveValidInvitation($token): BoardInvitation
    {
        $invitation = BoardInvitation::where('token', $token)->first();
        if (! $invitation) {
            abort(404, 'Không tìm thấy lời mời.');
        }
        if (! $invitation->accepted_at && $invitation->expires_at && $invitation->expires_at->isPast()) {
            $invitation->delete();
            abort(401, 'Lời mời này đã hết hạn.');
        }

        return $invitation;
    }

    // Đảm bảo người dùng đã đăng nhập và đúng email của lời mời; trả về redirect tới login nếu chưa, ngược lại null.
    private function ensureAuthenticatedInvitee(BoardInvitation $invitation)
    {
        if (! Auth::check()) {
            session(['url.intended' => URL::full()]);

            return redirect()->route('login.form')
                ->with('info', 'Vui lòng đăng nhập hoặc đăng ký để chấp nhận lời mời.');
        }
        if (Auth::user()->email !== $invitation->email) {
            Auth::logout();
            session(['url.intended' => URL::full()]);

            return redirect()->route('login.form')->with('error', 'Lời mời này dành cho một tài khoản email khác.');
        }

        return null;
    }

    // Đảm bảo người mời vẫn còn quyền quản lý bảng; abort 403 và xóa lời mời nếu không còn.
    private function ensureInviterStillAuthorized(BoardInvitation $invitation, Board $board): void
    {
        $inviter = $invitation->inviter;
        $inviterStillAuthorized = $inviter
            && ($inviter->id === $board->user_id
                || $inviter->hasBoardPermission($board, 'board_member_manager'));
        if (! $inviterStillAuthorized) {
            $invitation->delete();
            abort(403, 'Lời mời không còn hợp lệ vì người mời không còn quyền quản lý bảng.');
        }
    }

    public function updateMemberRole(Request $request, Board $board, User $member)
    {
        if (Auth::id() !== $board->user_id && ! Auth::user()->hasBoardPermission($board, 'board_member_manager')) {
            return redirect()->back()->with('error', 'Không có quyền.');
        }
        if ($member->id === $board->user_id) {
            return redirect()->back()->with('error', 'Không thể thay đổi vai trò của chủ sở hữu.');
        }

        $request->validate([
            'new_role_permission_name' => 'required|string|exists:permissions,name',
        ]);
        $newPermissionName = $request->input('new_role_permission_name');

        DB::transaction(function () use ($board, $member, $newPermissionName) {
            // 1. Revoke all existing board-specific permissions for this user on this board
            $this->revokeAllBoardPermissionsForUser($board, $member);

            // 2. Grant the new permission
            if (! $this->grantBoardPermission($board, $member, $newPermissionName)) {
                throw new \Exception("Failed to grant new permission {$newPermissionName}.");
            }
        });

        return redirect()->back()->with('success', 'Vai trò của thành viên đã được cập nhật.');
    }

    public function removeMember(Board $board, User $member)
    {
        if (Auth::id() !== $board->user_id && ! Auth::user()->hasBoardPermission($board, 'board_member_manager')) {
            return redirect()->back()->with('error', 'Không có quyền.');
        }
        if ($member->id === $board->user_id) {
            return redirect()->back()->with('error', 'Không thể xóa chủ sở hữu.');
        }

        DB::transaction(function () use ($board, $member) {
            $this->revokeAllBoardPermissionsForUser($board, $member);
            $this->cancelPendingHandoverRequests($board, $member);
            // Also cancel any pending invitations for this user on this board
            // If using invitations
            BoardInvitation::where('board_id', $board->id)
                ->where('email', $member->email)
                ->delete();
        });

        return redirect()->back()->with('success', 'Thành viên đã được xóa khỏi bảng.');
    }

    /**
     * Thành viên chỉ được rời board sau khi đã tự bàn giao hết task được giao.
     */
    public function leave(Board $board)
    {
        $user = Auth::user();

        if ($user->id === $board->user_id) {
            return response()->json([
                'message' => 'Chủ sở hữu không thể rời bảng. Hãy chuyển quyền sở hữu trước.',
            ], 422);
        }

        if (! $user->getRoleForBoard($board)) {
            abort(403, 'Bạn không còn là thành viên của bảng này.');
        }

        DB::transaction(function () use ($board, $user) {
            Board::query()->lockForUpdate()->findOrFail($board->id);
            $hasAssignedTasks = Task::query()
                ->whereHas('column', fn ($query) => $query->where('board_id', $board->id))
                ->whereHas('assignees', fn ($query) => $query->where('users.id', $user->id))
                ->lockForUpdate()
                ->exists();
            abort_if($hasAssignedTasks, 422, 'Hãy bàn giao toàn bộ công việc trước khi rời bảng.');

            $this->revokeAllBoardPermissionsForUser($board, $user);
            $this->cancelPendingHandoverRequests($board, $user);
        });

        return response()->json([
            'success' => true,
            'message' => 'Bạn đã rời bảng thành công.',
        ]);
    }

    public function cancelInvitation(Board $board, BoardInvitation $invitation) // If using BoardInvitations
    {
        if (
            (Auth::id() !== $board->user_id
                && ! Auth::user()->hasBoardPermission($board, 'board_member_manager'))
            || $invitation->board_id !== $board->id
        ) {
            abort(403);
        }
        $invitation->delete();

        return back()->with('success', 'Lời mời đã được hủy.');
    }
}
