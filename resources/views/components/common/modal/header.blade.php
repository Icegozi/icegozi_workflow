{{--
    Phần đầu (header) của modal.

    Props:
        title       : tiêu đề. Nếu để trống có thể truyền nội dung qua slot.
        titleId     : id cho thẻ tiêu đề (tuỳ chọn) — JS có thể cần để cập nhật text.
        dismissible : có hiển thị nút đóng (×) hay không. Mặc định true.

    Mọi class bổ sung sẽ gộp vào div.modal-header (vd 'bg-dark text-light').
--}}
@props([
    'title' => null,
    'titleId' => null,
    'dismissible' => true,
])

<div {{ $attributes->merge(['class' => 'modal-header']) }}>
    <h5 class="modal-title" @if ($titleId) id="{{ $titleId }}" @endif>
        {{ $title ?? $slot }}
    </h5>
    @if ($dismissible)
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
    @endif
</div>
