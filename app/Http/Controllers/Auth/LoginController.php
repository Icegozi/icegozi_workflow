<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Auth;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('login', 'password');
        $remember = $request->has('remember');

        if (User::login($credentials, $remember)) {
            $user = Auth::user();

            if ($user->is_admin) {
                return redirect()->route('admin.dashboard');
            } else {
                return redirect()->route('user.dashboard');
            }
        } else {
            return back()->withErrors([
                'login' => 'Thông tin đăng nhập không hợp lệ hoặc tài khoản đã bị khóa.',
            ])->withInput();
        }
    }

    public function logout()
    {
        User::logout();

        return redirect('/login');
    }
}
