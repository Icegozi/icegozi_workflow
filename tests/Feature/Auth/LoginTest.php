<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Môi trường container đặt APP_ENV=local nên CSRF không tự bỏ qua trong test.
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    private function makeUser(array $overrides = []): User
    {
        // password hash trong factory tương ứng chuỗi "password".
        return User::factory()->create($overrides);
    }

    public function test_dang_nhap_bang_email(): void
    {
        $user = $this->makeUser(['email' => 'a@example.com']);

        $this->post(route('login'), ['login' => 'a@example.com', 'password' => 'password'])
            ->assertRedirect(route('my-tasks.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_dang_nhap_bang_username(): void
    {
        $user = $this->makeUser(['username' => 'johndoe']);

        $this->post(route('login'), ['login' => 'johndoe', 'password' => 'password'])
            ->assertRedirect(route('my-tasks.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_tuong_thich_nguoc_field_email(): void
    {
        // Client cũ gửi 'email' thay vì 'login' vẫn phải đăng nhập được.
        $this->makeUser(['email' => 'legacy@example.com']);

        $this->post(route('login'), ['email' => 'legacy@example.com', 'password' => 'password'])
            ->assertRedirect(route('my-tasks.index'));

        $this->assertAuthenticated();
    }

    public function test_admin_duoc_dieu_huong_dashboard_admin(): void
    {
        $this->makeUser(['username' => 'boss', 'is_admin' => true]);

        $this->post(route('login'), ['login' => 'boss', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_tai_khoan_khong_active_bi_chan(): void
    {
        $this->makeUser(['username' => 'blocked', 'status' => 'banned']);

        $this->post(route('login'), ['login' => 'blocked', 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_sai_mat_khau_that_bai(): void
    {
        $this->makeUser(['username' => 'someone']);

        $this->post(route('login'), ['login' => 'someone', 'password' => 'wrong-pass'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_thieu_dinh_danh_bi_validate(): void
    {
        $this->post(route('login'), ['password' => 'password'])
            ->assertSessionHasErrors('login');
    }

    public function test_dang_nhap_username_khong_phan_biet_hoa_thuong(): void
    {
        // Username lưu lowercase; nhập hoa/thường bất kỳ vẫn phải khớp (độc lập collation DB).
        $user = $this->makeUser(['username' => 'johndoe']);

        $this->post(route('login'), ['login' => 'JohnDoe', 'password' => 'password'])
            ->assertRedirect(route('my-tasks.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_bi_gioi_han_toc_do_sau_5_lan(): void
    {
        $this->makeUser(['username' => 'victim']);

        // 5 lần sai đầu tiên còn được phép (trả về lỗi, không phải 429).
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['login' => 'victim', 'password' => 'wrong-pass']);
        }

        // Lần thứ 6 bị throttle:5,1 chặn.
        $this->post(route('login'), ['login' => 'victim', 'password' => 'wrong-pass'])
            ->assertStatus(429);
    }
}
