@extends('layouts.auth')

@section('title', 'Đăng ký tài khoản')

@section('content')
  <div class="register-box">
    <div class="card card-outline card-secondary">
    <div class="card-header text-center">
      <a href="#" class="h1"><b>My</b>App</a>
    </div>
    <div class="card-body">
      <p class="login-box-msg">Tạo tài khoản mới</p>

      <x-common.form :action="route('register')" method="POST">
      <x-common.text-input type="text" name="name" placeholder="Họ tên" :value="old('name')"
        icon="fas fa-user" required />

      <x-common.text-input type="email" name="email" placeholder="Email" :value="old('email', request('email'))"
        icon="fas fa-envelope" required />

      <x-common.text-input type="password" name="password" placeholder="Mật khẩu"
        icon="fas fa-lock" required />

      <x-common.text-input type="password" name="password_confirmation" placeholder="Nhập lại mật khẩu"
        icon="fas fa-lock" required />

      <div class="row">
        <div class="col-md-6 mb-2">
          <x-common.button variant="dark" class="btn-block font-weight-bold">
            Đăng ký
          </x-common.button>
        </div>
        <div class="col-md-6 d-flex align-items-center justify-content-md-end justify-content-center">
          <a href="{{ route('login.form') }}" class="text-dark" style="text-decoration: none;">
            Tôi đã có tài khoản
          </a>
        </div>
      </div>
      </x-common.form>

    </div>
    </div>
  </div>
@endsection