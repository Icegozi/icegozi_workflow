{{--
    Ô nhập liệu (text input) dùng chung, bọc trong input-group của AdminLTE/Bootstrap.
    Mọi thuộc tính HTML (name, id, value, placeholder, required, autofocus...) được truyền thẳng vào thẻ <input>.

    Props:
        type        : loại input (text, email, password, ...). Mặc định 'text'.
        icon        : class icon FontAwesome hiển thị ở cuối ô (vd 'fas fa-envelope'). Bỏ trống => không có icon.
        groupClass  : class cho div.input-group bao ngoài (mặc định 'mb-3').

    Cách dùng:
        <x-common.text-input type="email" name="email" placeholder="Email"
            :value="old('email')" icon="fas fa-envelope" required autofocus />
--}}
@props([
    'type' => 'text',
    'icon' => null,
    'groupClass' => 'mb-3',
])

<div class="input-group {{ $groupClass }}">
    <input type="{{ $type }}" {{ $attributes->merge(['class' => 'form-control']) }}>
    @if ($icon)
        <div class="input-group-append">
            <div class="input-group-text h-100"><span class="{{ $icon }}"></span></div>
        </div>
    @endif
</div>
