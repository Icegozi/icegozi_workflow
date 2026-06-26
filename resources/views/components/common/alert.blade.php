{{--
    Hộp thông báo (alert) dùng chung.

    Props:
        type : loại alert Bootstrap (success/danger/warning/info...). Mặc định 'info'.

    Cách dùng:
        <x-common.alert type="success" class="small p-2">{{ session('success_invite') }}</x-common.alert>
--}}
@props([
    'type' => 'info',
])

<div {{ $attributes->merge(['class' => 'alert alert-' . $type]) }} role="alert">
    {{ $slot }}
</div>
