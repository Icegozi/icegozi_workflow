<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Đăng xuất ngay những tài khoản đã bị khoá (status != active),
     * kể cả khi đang có session/cookie "remember me" còn hiệu lực.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && $user->status !== 'active') {
            User::logout();

            if ($request->expectsJson()) {
                abort(403, 'Tài khoản của bạn đã bị khoá.');
            }

            return redirect()->route('login.form')
                ->withErrors(['email' => 'Tài khoản của bạn đã bị khoá.']);
        }

        return $next($request);
    }
}
