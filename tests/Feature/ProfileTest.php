<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Môi trường container đặt APP_ENV=local nên CSRF không tự bỏ qua trong test.
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_khach_khong_vao_duoc_trang_ho_so(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login.form'));
    }

    public function test_giao_dien_duoc_luu_rieng_theo_tung_tai_khoan(): void
    {
        $firstUser = User::factory()->create(['theme' => 'light']);
        $secondUser = User::factory()->create(['theme' => 'light']);

        $this->actingAs($firstUser)
            ->putJson(route('profile.theme.update'), ['theme' => 'dark'])
            ->assertOk()
            ->assertJsonPath('theme', 'dark');

        $this->assertDatabaseHas('users', ['id' => $firstUser->id, 'theme' => 'dark']);
        $this->assertDatabaseHas('users', ['id' => $secondUser->id, 'theme' => 'light']);
    }

    public function test_chi_nhan_giao_dien_sang_hoac_toi(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson(route('profile.theme.update'), ['theme' => 'blue'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('theme');
    }

    public function test_cap_nhat_thong_tin_co_ban_va_mang_xa_hoi(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'name' => 'Tên Mới',
                'username' => 'ten_moi',
                'email' => 'moi@example.com',
                // thiếu scheme -> phải được tự thêm https://
                'social' => ['facebook' => 'facebook.com/me', 'website' => ''],
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Tên Mới', $user->name);
        $this->assertSame('ten_moi', $user->username);
        $this->assertSame('moi@example.com', $user->email);
        $this->assertSame('https://facebook.com/me', $user->social['facebook']);
        $this->assertArrayNotHasKey('website', $user->social ?? []);
    }

    public function test_username_trung_nguoi_khac_bi_chan(): void
    {
        User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create(['username' => 'mine']);

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'name' => $user->name,
                'username' => 'taken',
                'email' => $user->email,
            ])
            ->assertSessionHasErrors('username');
    }

    public function test_giu_username_email_cua_chinh_minh(): void
    {
        $user = User::factory()->create(['username' => 'keepme', 'email' => 'keep@example.com']);

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'name' => 'Đổi tên',
                'username' => 'keepme',
                'email' => 'keep@example.com',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_upload_avatar_luu_duong_dan_tuong_doi_va_xoa_anh_cu(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_url' => '/storage/avatars/old.png']);
        Storage::disk('public')->put('avatars/old.png', 'dummy');

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => UploadedFile::fake()->image('new.jpg', 100, 100),
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertStringStartsWith('/storage/avatars/', $user->avatar_url);
        // Đường dẫn tương đối, không baked host/port.
        $this->assertStringNotContainsString('http', $user->avatar_url);
        Storage::disk('public')->assertExists(substr($user->avatar_url, strlen('/storage/')));
        Storage::disk('public')->assertMissing('avatars/old.png');
    }

    public function test_chan_upload_file_khong_phai_anh(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php'),
            ])
            ->assertSessionHasErrors('avatar');
    }

    public function test_doi_mat_khau(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(password_verify('NewPass123', $user->fresh()->password));
    }

    public function test_khong_the_leo_thang_quyen_qua_ho_so(): void
    {
        // User thường gửi kèm is_admin/status -> phải bị bỏ qua (không nằm trong ProfileUpdateRequest).
        $user = User::factory()->create(['is_admin' => false, 'status' => 'active']);

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'is_admin' => 1,
                'status' => 'banned',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertFalse((bool) $user->is_admin);
        $this->assertSame('active', $user->status);
    }

    public function test_username_duoc_ha_ve_chu_thuong(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'name' => $user->name,
                'username' => 'MixedCase',
                'email' => $user->email,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('mixedcase', $user->fresh()->username);
    }

    public function test_social_url_khong_hop_le_bi_chan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                // "không phải url" có khoảng trắng -> sau khi prepend https:// vẫn không hợp lệ.
                'social' => ['facebook' => 'không phải url'],
            ])
            ->assertSessionHasErrors('social.facebook');
    }

    public function test_mat_khau_yeu_bi_chan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_email_trung_nguoi_khac_bi_chan(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'name' => $user->name,
                'username' => $user->username,
                'email' => 'taken@example.com',
            ])
            ->assertSessionHasErrors('email');
    }
}
