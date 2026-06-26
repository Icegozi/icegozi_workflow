{{--
    Khối trường biểu mẫu: nhãn (label) + nội dung (slot) + thông báo lỗi validate.

    Props:
        label : nhãn hiển thị phía trên (tuỳ chọn).
        name  : tên trường, dùng để gắn <label for> và tra lỗi @error($name).
        labelClass : class cho thẻ label (mặc định 'small font-weight-bold').

    Cách dùng:
        <x-common.form-field label="Email thành viên" name="email">
            <x-common.text-input type="email" name="email" id="email" groupClass=""
                class="form-control-sm @error('email') is-invalid @enderror" :value="old('email')" required />
        </x-common.form-field>
--}}
@props([
    'label' => null,
    'name' => null,
    'labelClass' => 'small font-weight-bold',
])

<div {{ $attributes->merge(['class' => 'form-group']) }}>
    @if ($label)
        <label @if ($name) for="{{ $name }}" @endif class="{{ $labelClass }}">{{ $label }}</label>
    @endif

    {{ $slot }}

    @if ($name)
        @error($name)
            <div class="invalid-feedback small d-block">{{ $message }}</div>
        @enderror
    @endif
</div>
