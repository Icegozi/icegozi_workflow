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
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => false,
            'status' => 'active',
        ])->save();

        return $user;
    }

    public static function login(array $credentials, bool $remember = false): bool
    {
        $user = self::where('email', $credentials['email'])->first();

        if (! $user || $user->status !== 'active') {
            return false;
        }

        if (Auth::attempt($credentials, $remember)) {
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
