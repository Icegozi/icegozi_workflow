@extends('layouts.auth')

@section('title', 'Đăng nhập')

@section('content')
  <div class="login-box">
    <div class="card card-outline card-secondary">
    <div class="card-header text-center">
      <a href="#" class="h1"><b>My</b>App</a>
    </div>

    <div class="card">
      <div class="card-body login-card-body">
      <p class="login-box-msg">Đăng nhập để bắt đầu phiên làm việc</p>

      <x-common.form :action="route('login')" method="POST">
        <x-common.text-input type="email" name="email" placeholder="Email" :value="old('email')"
          icon="fas fa-envelope" required autofocus />

        <x-common.text-input type="password" name="password" placeholder="Mật khẩu"
          icon="fas fa-lock" required />

        <div class="row">
        <div class="col-7">
          <div class="icheck-primary">
          <input type="checkbox" id="remember" name="remember">
          <label for="remember">
            Ghi nhớ đăng nhập
          </label>
          </div>
        </div>

        <div class="col-5 text-end">
          <x-common.button variant="dark" class="btn-block font-weight-bold">Đăng nhập</x-common.button>
        </div>
        </div>
      </x-common.form>

      <p class="mb-0 mt-3">
        <a href="{{ route('register') }}" class="text-dark" style="text-decoration: none;">Chưa có tài khoản? Đăng ký</a>
      </p>
      </div>
    </div>
    </div>
  @endsection