{{--
    Combobox (thẻ select) dùng chung.
    Mọi thuộc tính HTML (name, id, required, disabled, class bổ sung...) được truyền thẳng vào thẻ <select>.

    Props:
        options     : mảng [value => label] để sinh các <option>.
        selected    : giá trị đang được chọn.
        placeholder : nếu có, thêm 1 <option value="" disabled> ở đầu làm gợi ý chọn.

    Có thể truyền thêm <option> thủ công qua slot nếu cần.

    Cách dùng:
        <x-common.select name="status" id="userStatus"
            :options="['active' => 'Kích hoạt', 'inactive' => 'Không kích hoạt']"
            :selected="old('status', $user->status)" required />
--}}
@props([
    'options' => [],
    'selected' => null,
    'placeholder' => null,
])

<select {{ $attributes->merge(['class' => 'form-control']) }}>
    @if (! is_null($placeholder))
        <option value="" disabled {{ is_null($selected) || $selected === '' ? 'selected' : '' }}>
            {{ $placeholder }}
        </option>
    @endif

    @foreach ($options as $value => $label)
        <option value="{{ $value }}" {{ (string) $selected === (string) $value ? 'selected' : '' }}>
            {{ $label }}
        </option>
    @endforeach

    {{ $slot }}
</select>
