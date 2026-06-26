<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'MyApp') }}</title>

    <link rel="shortcut icon" type="image/icon" href="{{ asset_min('favicon.ico') }}" />

    {{-- Fonts --}}
    <link
        href="https://fonts.googleapis.com/css?family=Poppins:100,200,300,400,500,600,700,800,900"
        rel="stylesheet">

    {{-- Vendor CSS (giữ nguyên theme AdminLTE + Bootstrap + FontAwesome) --}}
    <link rel="stylesheet" href="{{ asset_min('plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset_min('plugins/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset_min('plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset_min('plugins/jquery/jquery-ui.css') }}">
    <link rel="stylesheet" href="{{ asset_min('assets/css/adminlte.css') }}">
    <link rel="stylesheet" href="{{ asset_min('assets/css/user.css') }}">
    <link rel="stylesheet" href="{{ asset_min('assets/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset_min('assets/css/components.css') }}">
    <link rel="stylesheet" href="{{ asset_min('assets/css/column.css') }}">

    {{-- Landing page theme (welcome) --}}
    <link rel="stylesheet" href="{{ asset_min('assets/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset_min('assets/css/responsive.css') }}">

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>

<body class="hold-transition layout-top-nav">
    @inertia
</body>

</html>
