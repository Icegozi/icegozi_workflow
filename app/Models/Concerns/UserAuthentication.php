<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

trait UserAuthentication
{
    public static function register(array $data)
    {
        // is_admin/status không nằm trong $fillable nên dùng forceFill để gán có chủ đích.
        $user = new self();
        $user->forceFill([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => false,
            'status' => 'active',
        ])->save();

        return $user;
    }

    /**
     * Đăng nhập bằng username HOẶC email. $credentials['login'] là định danh nhập vào;
     * nếu là email hợp lệ thì tra theo cột email, ngược lại tra theo username.
     */
    public static function login(array $credentials, bool $remember = false): bool
    {
        $login = $credentials['login'] ?? $credentials['email'] ?? null;
        if (! $login) {
            return false;
        }

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        if ($field === 'username') {
            $login = mb_strtolower($login);
        }

        $user = self::where($field, $login)->first();

        if (! $user || $user->status !== 'active') {
            return false;
        }

        if (Auth::attempt([$field => $login, 'password' => $credentials['password']], $remember)) {
            session()->regenerate();

            return true;
        }

        return false;
    }

    public static function logout()
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }
}
