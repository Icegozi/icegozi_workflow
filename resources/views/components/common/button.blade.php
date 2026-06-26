{{--
    Nút bấm dùng chung.

    Props:
        type    : kiểu nút (submit/button/reset). Mặc định 'submit'.
        variant : biến thể Bootstrap (vd 'primary', 'dark', 'outline-danger'). Mặc định 'primary'.
        icon    : class icon FontAwesome hiển thị trước nhãn (tuỳ chọn).

    Mọi class bổ sung (btn-sm, btn-block...) truyền thêm sẽ được gộp vào.

    Cách dùng:
        <x-common.button variant="dark" class="btn-block font-weight-bold">Đăng nhập</x-common.button>
        <x-common.button variant="outline-dark" icon="fas fa-paper-plane">Gửi lời mời</x-common.button>
--}}
@props([
    'type' => 'submit',
    'variant' => 'primary',
    'icon' => null,
])

<button type="{{ $type }}" {{ $attributes->merge(['class' => 'btn btn-' . $variant]) }}>
    @if ($icon)
        <i class="{{ $icon }} mr-1"></i>
    @endif
    {{ $slot }}
</button>
