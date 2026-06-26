{{--
    Thẻ (card) dùng chung theo phong cách AdminLTE/Bootstrap.

    Props:
        title       : tiêu đề ở header (tuỳ chọn). Có thể thay bằng slot 'header'.
        icon        : class icon FontAwesome đứng trước tiêu đề (tuỳ chọn).
        headerClass : class cho div.card-header (mặc định 'py-3 bg-secondary').
        bodyClass   : class cho div.card-body (mặc định 'card-body').

    Slot:
        header (tuỳ chọn) : ghi đè toàn bộ nội dung header.
        slot mặc định     : nội dung body.

    Mọi class bổ sung sẽ gộp vào div.card (vd 'shadow mb-4').

    Cách dùng:
        <x-common.card title="Mời thành viên mới" icon="fas fa-user-plus" class="shadow mb-4">
            ... nội dung ...
        </x-common.card>
--}}
@props([
    'title' => null,
    'icon' => null,
    'headerClass' => 'py-3 bg-secondary',
    'bodyClass' => 'card-body',
])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if (isset($header) || $title)
        <div class="card-header d-flex flex-row align-items-center justify-content-between {{ $headerClass }}">
            @isset($header)
                {{ $header }}
            @else
                <h6 class="m-0 font-weight-bold text-white">
                    @if ($icon)
                        <i class="{{ $icon }} mr-2"></i>
                    @endif
                    {{ $title }}
                </h6>
            @endisset
        </div>
    @endif

    <div class="{{ $bodyClass }}">
        {{ $slot }}
    </div>
</div>
