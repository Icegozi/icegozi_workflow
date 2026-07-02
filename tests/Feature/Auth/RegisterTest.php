<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Môi trường container đặt APP_ENV=local nên CSRF không tự bỏ qua trong test.
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_dang_ky_thanh_cong_va_ha_username_ve_chu_thuong(): void
    {
        $this->post(route('register'), [
            'name' => 'Người Mới',
            'username' => 'NguoiMoi',
            'email' => 'moi@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertRedirect(route('login'));

        $user = User::where('email', 'moi@example.com')->first();
        $this->assertNotNull($user);
        // Username lưu lowercase để login độc lập collation.
        $this->assertSame('nguoimoi', $user->username);
    }

    public function test_username_trung_bi_chan(): void
    {
        User::factory()->create(['username' => 'taken']);

        $this->post(route('register'), [
            'name' => 'X',
            'username' => 'taken',
            'email' => 'x@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertSessionHasErrors('username');
    }

    public function test_username_co_ky_tu_khong_hop_le_bi_chan(): void
    {
        $this->post(route('register'), [
            'name' => 'X',
            'username' => 'ten co dau.cham',
            'email' => 'x@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertSessionHasErrors('username');

        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }
}
