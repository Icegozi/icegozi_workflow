<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait UserManagement
{
    public static function addUser(array $data): ?User
    {
        // Chỉ gọi từ luồng quản trị; gán is_admin/status có chủ đích qua forceFill.
        $user = new self();
        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => $data['is_admin'] ?? false,
            'status' => $data['status'] ?? 'active',
        ])->save();

        return $user;
    }

    public static function updateUserById(int $id, array $data): bool
    {
        $user = self::find($id);
        if (! $user) {
            return false;
        }

        // Chỉ chấp nhận các trường được phép; is_admin/status gán có chủ đích (luồng quản trị).
        $fields = [];
        foreach (['name', 'email', 'is_admin', 'status'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }
        if (! empty($data['password'])) {
            $fields['password'] = Hash::make($data['password']);
        }

        return $user->forceFill($fields)->save();
    }

    public static function deleteUserById(int $id): bool
    {
        if (auth()->id() === $id) {
            return false;
        }
        $user = self::find($id);
        if (! $user) {
            return false;
        }

        return $user->delete();
    }

    public static function searchUsers(string $keyword)
    {
        return self::where(function ($query) use ($keyword) {
            $query->where('name', 'like', "%{$keyword}%")
                ->orWhere('email', 'like', "%{$keyword}%");
        })->get();
    }
}
