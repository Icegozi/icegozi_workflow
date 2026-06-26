@extends('layouts.admin')

@section('title', 'Thêm người dùng')

@section('content')
    <h3>Thêm tài khoản mới</h3>
    @if ($errors->any())
        <script>
            let errorMessages = {!! json_encode($errors->all()) !!};
            alert(errorMessages.join("\n"));
        </script>
    @endif

    <div id="user-create-container">
        <x-common.form id="createUserForm" method="POST" :action="route('admin.user.store')">

            <table class="table table-bordered mt-4">
                <tbody>
                    <tr>
                        <th scope="row">Họ và tên</th>
                        <td><x-common.text-input type="text" id="userName" name="name" :value="old('name')" groupClass="" required /></td>
                    </tr>

                    <tr>
                        <th scope="row">Email</th>
                        <td><x-common.text-input type="email" id="userEmail" name="email" :value="old('email')" groupClass="" required /></td>
                    </tr>

                    <tr>
                        <th scope="row" colspan="2">
                            <hr>
                            <p class="text-muted"><em>Bạn nên đặt mật khẩu có ít nhất 8 kí tự, ít nhất một chữ hoa, một chữ thường và 1 ki tự đặt biệt cho tài khoản mới.</em></p>
                        </th>
                    </tr>

                    <tr>
                        <th scope="row">Mật khẩu</th>
                        <td><x-common.text-input type="password" id="userPassword" name="password" placeholder="Nhập mật khẩu" groupClass="" required /></td>
                    </tr>

                    <tr>
                        <th scope="row">Xác nhận mật khẩu</th>
                        <td><x-common.text-input type="password" id="userPasswordConfirmation" name="password_confirmation" placeholder="Xác nhận mật khẩu" groupClass="" required /></td>
                    </tr>

                    <tr>
                        <th scope="row" colspan="2">
                            <hr>
                        </th>
                    </tr>

                    <tr>
                        <th scope="row">Trạng thái</th>
                        <td>
                            <x-common.select id="userStatus" name="status" required
                                :options="['active' => 'Kích hoạt', 'inactive' => 'Không kích hoạt', 'banned' => 'Bị khóa']"
                                :selected="old('status')" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Là quản trị viên</th>
                        <td>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="isAdmin" name="is_admin" value="1">
                                <label class="form-check-label" for="isAdmin">Là quản trị viên (Is Admin)</label>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="d-flex justify-content-end">
                <x-common.button variant="outline-dark" class="mr-2">Thêm tài khoản</x-common.button>
                <a href="{{ route('admin.user.index') }}" class="btn btn-danger">Hủy</a>
            </div>

        </x-common.form>
    </div>
@endsection
