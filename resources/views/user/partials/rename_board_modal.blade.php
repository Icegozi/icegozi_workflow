<x-common.modal id="renameBoardModal" size="modal-sm">
    <x-common.form id="rename-board-form" method="PUT" action="#">
        <x-common.modal.header title="Nhập tên bảng mới" titleId="renameBoardModalLabel"
            class="bg-white border-0 pb-2" />

        <x-common.modal.body class="pt-1">
            <input type="hidden" id="rename-board-id" name="board_id">
            <input type="hidden" id="rename-board-current-name">
            <div class="form-group mb-0">
                <x-common.text-input type="text" id="rename-board-new-name" name="name" groupClass=""
                    required maxlength="255" placeholder="Nhập tên..." />
            </div>
            <div id="rename-error-message" class="text-danger small mt-2"></div>
        </x-common.modal.body>

        <x-common.modal.footer class="justify-content-end border-0 pt-1">
            <x-common.button type="button" variant="light" class="btn-sm" data-dismiss="modal">Cancel</x-common.button>
            <x-common.button variant="primary" class="btn-sm px-3">OK</x-common.button>
        </x-common.modal.footer>
    </x-common.form>
</x-common.modal>
