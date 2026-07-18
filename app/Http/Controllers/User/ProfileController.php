<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Concerns\HandlesProfileMedia;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class ProfileController extends Controller
{
    use HandlesProfileMedia;

    public function edit()
    {
        $user = Auth::user();

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'social' => $user->social ?? [],
                'is_admin' => (bool) $user->is_admin,
                'created_at' => optional($user->created_at)->format('d/m/Y'),
            ],
        ]);
    }

    public function update(ProfileUpdateRequest $request)
    {
        $user = Auth::user();
        $data = $request->validated();

        $user->name = $data['name'];
        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->social = $this->cleanSocial($data['social'] ?? []);
        $user->avatar_url = $this->storeAvatar($request->file('avatar'), $user->avatar_url);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return back()->with('success', 'Đã cập nhật hồ sơ.');
    }

    public function updateTheme(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        $request->user()->update(['theme' => $data['theme']]);

        return response()->json(['theme' => $data['theme']]);
    }
}
