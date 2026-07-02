<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_admin_tao_tai_khoan_voi_avatar_va_social(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.user.store'), [
                'name' => 'Nhân viên',
                'username' => 'NhanVien',
                'email' => 'nv@example.com',
                'password' => 'Password1@',
                'password_confirmation' => 'Password1@',
                'status' => 'active',
                'is_admin' => false,
                // thiếu scheme -> tự thêm https://; key rỗng -> bị loại.
                'social' => ['facebook' => 'facebook.com/nv', 'twitter' => ''],
                'avatar' => UploadedFile::fake()->image('a.jpg', 80, 80),
            ])
            ->assertRedirect(route('admin.user.index'));

        $user = User::where('email', 'nv@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('nhanvien', $user->username);         // lowercased
        $this->assertStringStartsWith('/storage/avatars/', $user->avatar_url);
        $this->assertSame('https://facebook.com/nv', $user->social['facebook']);
        $this->assertArrayNotHasKey('twitter', $user->social);
        Storage::disk('public')->assertExists(substr($user->avatar_url, strlen('/storage/')));
    }

    public function test_admin_doi_avatar_thi_xoa_anh_cu(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['avatar_url' => '/storage/avatars/old.png']);
        Storage::disk('public')->put('avatars/old.png', 'dummy');

        $this->actingAs($admin)
            ->post(route('admin.user.update', $target->id), [
                '_method' => 'put',
                'name' => $target->name,
                'username' => $target->username,
                'email' => $target->email,
                'status' => $target->status,
                'is_admin' => false,
                'avatar' => UploadedFile::fake()->image('new.jpg', 80, 80),
            ])
            ->assertRedirect(route('admin.user.index'));

        $target->refresh();
        $this->assertStringStartsWith('/storage/avatars/', $target->avatar_url);
        Storage::disk('public')->assertMissing('avatars/old.png');
    }

    public function test_de_trong_username_khi_sua_khong_xoa_username_cu(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['username' => 'keepme']);

        $this->actingAs($admin)
            ->post(route('admin.user.update', $target->id), [
                '_method' => 'put',
                'name' => 'Tên đổi',
                'username' => '',
                'email' => $target->email,
                'status' => $target->status,
                'is_admin' => false,
            ])
            ->assertRedirect(route('admin.user.index'));

        // Patch C: username rỗng -> giữ nguyên, không bị null hoá.
        $this->assertSame('keepme', $target->fresh()->username);
    }

    public function test_user_thuong_khong_vao_duoc_quan_ly(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        // Middleware IsAdmin redirect về '/' cho non-admin (không abort 403).
        $this->actingAs($user)
            ->get(route('admin.user.index'))
            ->assertRedirect('/');
    }
}
