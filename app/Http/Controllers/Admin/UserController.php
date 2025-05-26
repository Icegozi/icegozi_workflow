<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::paginate(20);
        return view('admin.account.index', compact('users'));
    }

    public function create(Request $request)
    {
        return view('admin.account.create');
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
        User::addUser($request->all());
        return redirect()
            ->route('admin.user.index')
            ->with('success', 'Tài khoản đã được tạo thành công.');
    }

    public function show($id)
    {
        $user = User::find($id);

        return view('admin.account.show', compact('user'));
    }

    public function update(UserRequest $request, $id)
    {
        $ok = User::updateUserById($id, $request->all());

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
            return Response::json([
                'success' => true,
                'message' => 'Đã xóa người dùng',
            ]);
        }

        return Response::json([
            'success' => false,
            'message' => 'Không thể tự xóa chính mình',
        ], 404);
    }

    public function getUserList(Request $request)
    {
        $search = $request->input('search');
        $query = User::select('id', 'name');

       if ($search) {
            $search = strtolower($search);
            $search_ascii = Str::ascii($search);

            $query->where(function ($q) use ($search_ascii) {
                $q->whereRaw("LOWER(name) COLLATE utf8mb4_general_ci LIKE ?", ["%{$search_ascii}%"])
                ->orWhereRaw("LOWER(email) COLLATE utf8mb4_general_ci LIKE ?", ["%{$search_ascii}%"]);
            });
        }

        $users = $query->limit(20)->get();

        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => route('admin.user.show', ['id' => $user->id]),
                'text' => $user->name
            ];
        }

        return response()->json(['results' => $results]);
    }
}
