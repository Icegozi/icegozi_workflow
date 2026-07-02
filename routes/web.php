<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\StatusController as AdminStatusController;
use App\Http\Controllers\Admin\TemplateController as AdminTemplateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\User\AssigneeController;
use App\Http\Controllers\User\AttachmentController;
use App\Http\Controllers\User\BoardController;
use App\Http\Controllers\User\BoardMembershipController;
use App\Http\Controllers\User\ChartSettingController;
use App\Http\Controllers\User\ChecklistController;
use App\Http\Controllers\User\ColumnController;
use App\Http\Controllers\User\CommentController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\LabelController;
use App\Http\Controllers\User\MyTaskController;
use App\Http\Controllers\User\NotificationController;
use App\Http\Controllers\User\OverviewTaskController;
use App\Http\Controllers\User\TaskController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Trang chủ
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('welcome');

// ==== AUTHENTICATION ====
// Đăng xuất
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Các route cho khách chưa đăng nhập
Route::middleware('guest')->group(function () {
    // Đăng ký
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register.form');
    Route::post('/register', [RegisterController::class, 'register'])->name('register')->middleware('throttle:10,1');

    // Đăng nhập
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login.form');
    Route::post('/login', [LoginController::class, 'login'])->name('login')->middleware('throttle:5,1');
});

// ==== ROUTE CHUNG CHO USER & ADMIN ====
Route::middleware(['auth', 'active'])->group(function () {

    // Dashboard chung, tự điều hướng theo role
    Route::get('/dashboard', function () {
        return auth()->user()->is_admin
            ? redirect()->route('admin.dashboard')
            : redirect()->route('user.dashboard');
    })->name('dashboard');

    // Dashboard riêng cho user thường
    Route::get('/user/dashboard', [UserDashboardController::class, 'index'])->name('user.dashboard');
    Route::resource('boards', BoardController::class)->except([
        'index',
        'create',
        'edit',
    ]);

    Route::post('/boards', [BoardController::class, 'store'])->name('boards.store');
    Route::post('/boards/{board}/duplicate', [BoardController::class, 'duplicate'])->name('boards.duplicate');

    // Column Routes
    Route::post('/boards/{board}/columns', [ColumnController::class, 'store'])->name('columns.store');
    Route::put('/boards/{board}/columns/{column}', [ColumnController::class, 'update'])->name('columns.update');
    Route::delete('/boards/{board}/columns/{column}', [ColumnController::class, 'destroy'])->name('columns.destroy');
    Route::post('/boards/{board}/columns/reorder', [ColumnController::class, 'reorder'])->name('columns.reorder');

    // --- Task Routes ---
    Route::post('/columns/{column}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{taskCode}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('/tasks/update-position', [TaskController::class, 'updatePosition'])->name('tasks.updatePosition');

    // --- Label Routes ---
    Route::get('/boards/{board}/labels', [LabelController::class, 'index'])->name('labels.index');
    Route::post('/boards/{board}/labels', [LabelController::class, 'store'])->name('labels.store');
    Route::delete('/labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy');
    Route::post('/tasks/{task}/labels', [LabelController::class, 'attach'])->name('tasks.labels.attach');
    Route::delete('/tasks/{task}/labels/{label}', [LabelController::class, 'detach'])->name('tasks.labels.detach');

    // --- "Task của tôi" (cross-board) ---
    Route::get('/my-tasks', [MyTaskController::class, 'index'])->name('my-tasks.index');

    // --- Thông báo (in-app) ---
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.readAll');

    // --- Nhật ký hoạt động của bảng ---
    Route::get('/boards/{board}/activity', [BoardController::class, 'activity'])->name('boards.activity');

    // --- Số liệu phân tích của bảng ---
    Route::get('/boards/{board}/analytics', [BoardController::class, 'analytics'])->name('boards.analytics');

    // --- Thiết lập biểu đồ (theo user) ---
    Route::get('/chart-settings/{scope}', [ChartSettingController::class, 'show'])->name('chart-settings.show');
    Route::put('/chart-settings/{scope}', [ChartSettingController::class, 'update'])->name('chart-settings.update');

    // comment
    Route::post('/tasks/{task}/comments', [CommentController::class, 'store'])->name('comments.store');
    // Route::put('/tasks/{task}/comments/{commentId}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('/tasks/{task}/comments/{commentId}', [CommentController::class, 'destroy'])
        ->name('comments.destroy');

    // Attachment
    Route::get('/tasks/{task}/attachments', [AttachmentController::class, 'index'])->name('attachments.index');
    Route::post('/tasks/{task}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
        ->name('attachments.download');

    // --- Checklist
    Route::get('/tasks/{task}/checklists', [ChecklistController::class, 'index'])->name('checklists.index');
    Route::post('/tasks/{task}/checklists', [ChecklistController::class, 'store'])->name('checklists.store');
    Route::put('/checklists/{checklist}', [ChecklistController::class, 'update'])->name('checklists.update');
    Route::delete('/checklists/{checklist}', [ChecklistController::class, 'destroy'])->name('checklists.destroy');
    Route::post('/tasks/{task}/checklists/reorder', [ChecklistController::class, 'reorder'])
        ->name('checklists.reorder');

    // Board Membership and Invitations
    Route::get('/boards/{board}/settings', [BoardMembershipController::class, 'settings'])->name('boards.settings');
    Route::post('/boards/{board}/invite', [BoardMembershipController::class, 'inviteMember'])
        ->name('boards.invite')
        ->middleware('throttle:20,1');
    Route::post('/boards/{board}/members/{member}/update-role', [BoardMembershipController::class, 'updateMemberRole'])
        ->name('boards.members.updateRole');
    Route::delete('/boards/{board}/members/{member}/remove', [BoardMembershipController::class, 'removeMember'])
        ->name('boards.members.remove');
    Route::delete(
        '/boards/{board}/invitations/{invitation}/cancel',
        [BoardMembershipController::class, 'cancelInvitation']
    )->name('boards.invitations.cancel');
    Route::get(
        '/invitations/accept/{token}',
        [App\Http\Controllers\User\BoardMembershipController::class, 'acceptInvitation']
    )
        ->name('invitations.accept')
        ->middleware('signed');

    // ---Assignee
    Route::post('/tasks/{task}/assignees', [AssigneeController::class, 'store'])->name('tasks.assignees.store');
    Route::put('/tasks/{task}/assignees/{user}', [AssigneeController::class, 'update'])->name('tasks.assignee.update');
    Route::delete('/tasks/{task}/assignees/{user}', [AssigneeController::class, 'destroy'])
        ->name('tasks.assignees.destroy');
    Route::get('/boards/{board}/assigned-users', [AssigneeController::class, 'assignedUsers'])
        ->name('boards.assignedUsers');

    // overview
    Route::get('/board/overview/{board_id}', [OverviewTaskController::class, 'getTaskOverlayData'])
        ->name('board.overview.index');

    Route::middleware('is_admin')->group(function () {
        Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/admin/dashboard/growth', [AdminDashboardController::class, 'getGrowth'])
            ->name('admin.dashboard.growth');

        Route::prefix('users')->group(function () {
            Route::get('admin/management/user', [UserController::class, 'index'])->name('admin.user.index');
            Route::get('admin/management/user/sidebar', [UserController::class, 'getUserList'])
                ->name('admin.user.sidebar');
            Route::get('admin/management/user/create', [UserController::class, 'create'])->name('admin.user.create');
            Route::post('admin/management/user/store', [UserController::class, 'store'])->name('admin.user.store');
            Route::get('admin/management/user/{id}', [UserController::class, 'show'])->name('admin.user.show');
            Route::put('admin/management/user/{id}/update', [UserController::class, 'update'])
                ->name('admin.user.update');
            Route::delete('admin/management/user/{id}/delete', [UserController::class, 'destroy'])
                ->name('admin.user.destroy');
        });

        // Quản lý mẫu bảng
        Route::prefix('admin/management/template')->group(function () {
            Route::get('/', [AdminTemplateController::class, 'index'])->name('admin.template.index');
            Route::get('/create', [AdminTemplateController::class, 'create'])->name('admin.template.create');
            Route::post('/', [AdminTemplateController::class, 'store'])->name('admin.template.store');
            Route::get('/{template}/edit', [AdminTemplateController::class, 'edit'])->name('admin.template.edit');
            Route::put('/{template}', [AdminTemplateController::class, 'update'])->name('admin.template.update');
            Route::delete('/{template}', [AdminTemplateController::class, 'destroy'])->name('admin.template.destroy');
        });

        // Quản lý trạng thái (global)
        Route::prefix('admin/management/status')->group(function () {
            Route::get('/', [AdminStatusController::class, 'index'])->name('admin.status.index');
            Route::get('/create', [AdminStatusController::class, 'create'])->name('admin.status.create');
            Route::post('/', [AdminStatusController::class, 'store'])->name('admin.status.store');
            Route::get('/{status}/edit', [AdminStatusController::class, 'edit'])->name('admin.status.edit');
            Route::put('/{status}', [AdminStatusController::class, 'update'])->name('admin.status.update');
            Route::delete('/{status}', [AdminStatusController::class, 'destroy'])->name('admin.status.destroy');
        });
    });
});
