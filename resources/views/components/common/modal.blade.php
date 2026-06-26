{{--
    Popup (modal Bootstrap) dùng chung — phần khung.
    Toàn bộ nội dung bên trong .modal-content do người dùng truyền qua slot (có thể bọc <form> tuỳ ý),
    nhờ vậy giữ nguyên được mọi id/class mà JS đang phụ thuộc.

    Props:
        id           : id của modal (bắt buộc) — JS dùng để mở/đóng.
        size         : class kích thước ('modal-sm' | 'modal-lg' | 'modal-xl'). Mặc định ''.
        centered     : căn giữa theo chiều dọc. Mặc định true.
        scrollable   : cho phép cuộn nội dung. Mặc định false.
        contentClass : class bổ sung cho .modal-content (mặc định 'border-0 shadow rounded').

    Đi kèm các component con để dựng phần đầu/thân/chân:
        <x-common.modal.header>, <x-common.modal.body>, <x-common.modal.footer>

    Cách dùng:
        <x-common.modal id="renameBoardModal" size="modal-sm">
            <x-common.form id="rename-board-form" method="PUT" action="#">
                <x-common.modal.header title="Nhập tên bảng mới" />
                <x-common.modal.body> ... </x-common.modal.body>
                <x-common.modal.footer> ... </x-common.modal.footer>
            </x-common.form>
        </x-common.modal>
--}}
@props([
    'id',
    'size' => '',
    'centered' => true,
    'scrollable' => false,
    'contentClass' => 'border-0 shadow rounded',
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" role="dialog"
    aria-labelledby="{{ $id }}Label" aria-hidden="true" {{ $attributes }}>
    <div
        class="modal-dialog {{ $centered ? 'modal-dialog-centered' : '' }} {{ $scrollable ? 'modal-dialog-scrollable' : '' }} {{ $size }}"
        role="document">
        <div class="modal-content {{ $contentClass }}">
            {{ $slot }}
        </div>
    </div>
</div>
