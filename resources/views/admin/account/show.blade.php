@extends('layouts.admin')

@section('title', 'Sửa người dùng')

@section('content')
    <h3>Cập nhật tài khoản</h3>

    <div id="user-edit-container">
        <x-common.form id="editUserForm" method="PUT" :action="route('admin.user.update', $user->id)">

            <!-- Hidden input for User ID -->
            <input type="hidden" id="userId" name="id" value="{{ $user->id }}">

            <table class="table table-bordered mt-4">
                <tbody>
                    <tr>
                        <th scope="row">Họ và tên</th>
                        <td><x-common.text-input type="text" id="userName" name="name"
                                :value="old('name', $user->name)" groupClass="" required /></td>
                    </tr>

                    <tr>
                        <th scope="row">Email</th>
                        <td><x-common.text-input type="email" id="userEmail" name="email"
                                :value="old('email', $user->email)" groupClass="" required /></td>
                    </tr>

                    <tr>
                        <th scope="row" colspan="2">
                            <hr>
                            <p class="text-muted"><em>Để trống các trường mật khẩu nếu bạn không muốn thay đổi.</em></p>
                        </th>
                    </tr>

                    <tr>
                        <th scope="row">Mật khẩu mới</th>
                        <td><x-common.text-input type="password" id="userPassword" name="password"
                                placeholder="Nhập mật khẩu mới" groupClass="" /></td>
                    </tr>

                    <tr>
                        <th scope="row">Xác nhận mật khẩu mới</th>
                        <td><x-common.text-input type="password" id="userPasswordConfirmation"
                                name="password_confirmation" placeholder="Xác nhận mật khẩu mới" groupClass="" /></td>
                    </tr>

                    <tr>
                        <th scope="row" colspan="2">
                            <hr>
                        </th>
                    </tr>

                    <tr>
                        <th scope="row">Trạng thái</th>
                        <td>
                            <x-common.select id="userStatus" name="status"
                                :options="['active' => 'Kích hoạt', 'inactive' => 'Không kích hoạt', 'banned' => 'Bị khóa']"
                                :selected="old('status', $user->status)" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Là quản trị viên</th>
                        <td>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="isAdmin" name="is_admin"
                                    value="1" {{ $user->is_admin ? 'checked' : '' }}>
                                <label class="form-check-label" for="isAdmin">Là quản trị viên (Is Admin)</label>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Ngày tạo</th>
                        <td>{{ $user->created_at }}</td>
                    </tr>

                    <tr>
                        <th scope="row">Cập nhật lần cuối</th>
                        <td>{{ $user->updated_at }}</td>
                    </tr>

                    <tr>
                        <th scope="row">Email đã xác thực lúc</th>
                        <td>{{ $user->email_verified_at ? $user->email_verified_at : 'Chưa xác thực' }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="d-flex justify-content-end">
                <x-common.button variant="outline-dark" class="mr-2">Lưu thay đổi</x-common.button>
                <a href="{{ route('admin.user.index') }}" class="btn btn-danger">Hủy</a>
            </div>

        </x-common.form>
    </div>
@endsection

@if ($errors->any())
    <script>
        let errorMessages = "";
        @foreach ($errors->all() as $error)
            errorMessages += "{{ $error }}\n";
        @endforeach

        alert(errorMessages);
    </script>
@endif

@if (session('error'))
    <script>
        alert("{{ session('error') }}");
    </script>
@endif

@if (session('success'))
    <script>
        alert("{{ session('success') }}");
    </script>
@endif
