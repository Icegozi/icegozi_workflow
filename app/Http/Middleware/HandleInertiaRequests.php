<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => (bool) $user->is_admin,
                ] : null,
            ],
            // Flash messages dùng chung cho toàn bộ trang Vue
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'csrf_token' => fn () => csrf_token(),
            // Danh sách bảng sở hữu (cho dropdown "Mời thành viên" trên topbar)
            'ownedBoards' => fn () => $user
                ? $user->boardsOwned()->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values()
                : [],
        ]);
    }
}
