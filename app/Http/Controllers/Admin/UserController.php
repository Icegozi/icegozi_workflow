<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesProfileMedia;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Inertia\Inertia;

class UserController extends Controller
{
    use HandlesProfileMedia;

    public function index()
    {
        $users = User::paginate(20);

        return Inertia::render('Admin/Accounts/Index', compact('users'));
    }

    public function create()
    {
        return Inertia::render('Admin/Accounts/Create');
    }

    public function search(Request $request)
    {
        $users = User::searchUsers($request->query('q'))->paginate(20);

        return Response::json([
            'success' => true,
            'users' => $users,
        ]);
    }

    public function store(UserRequest $request)
    {
        $data = $request->validated();
        $data['avatar_url'] = $this->storeAvatar($request->file('avatar'));
        $data['social'] = $this->cleanSocial($data['social'] ?? []);

        try {
            User::addUser($data);
        } catch (\Throwable $e) {
            Log::error('Admin create user failed: ' . $e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'Không thể tạo tài khoản, vui lòng thử lại.');
        }

        return redirect()
            ->route('admin.user.index')
            ->with('success', 'Tài khoản đã được tạo thành công.');
    }

    public function show($id)
    {
        $user = User::findOrFail($id);

        return Inertia::render('Admin/Accounts/Edit', compact('user'));
    }

    public function update(UserRequest $request, $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validated();
        $data['avatar_url'] = $this->storeAvatar($request->file('avatar'), $user->avatar_url);
        $data['social'] = $this->cleanSocial($data['social'] ?? []);

        try {
            $ok = User::updateUserById($id, $data);
        } catch (\Throwable $e) {
            Log::error('Admin update user failed: ' . $e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'Không thể cập nhật tài khoản, vui lòng thử lại.');
        }

        if ($ok) {
            return redirect()
                ->route('admin.user.index')
                ->with('success', 'Cập nhật người dùng thành công!');
        }

        return redirect()
            ->back()
            ->withErrors(['error' => 'Không tìm thấy người dùng hoặc cập nhật thất bại']);
    }

    public function destroy($id)
    {
        $ok = User::deleteUserById($id);

        if ($ok) {
            return redirect()->route('admin.user.index')->with('success', 'Đã xóa người dùng.');
        }

        return redirect()->back()->with('error', 'Không thể tự xóa chính mình.');
    }

    public function getUserList(Request $request)
    {
        $search = $request->input('search');
        $query = User::select('id', 'name');

        if ($search) {
            $search = strtolower($search);
            $search_ascii = Str::ascii($search);

            $query->where(function ($q) use ($search_ascii) {
                $q->whereRaw('LOWER(name) COLLATE utf8mb4_general_ci LIKE ?', ["%{$search_ascii}%"])
                    ->orWhereRaw('LOWER(email) COLLATE utf8mb4_general_ci LIKE ?', ["%{$search_ascii}%"]);
            });
        }

        $users = $query->limit(20)->get();

        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => route('admin.user.show', ['id' => $user->id]),
                'text' => $user->name,
            ];
        }

        return response()->json(['results' => $results]);
    }
}
