import { reactive } from 'vue';

export const appAlert = reactive({
    isOpen: false,
    message: '',
    mode: 'alert',
    type: 'danger',
    inputValue: '',
});

let confirmResolver = null;

export const showAppAlert = (message, type = 'danger') => {
    appAlert.message = String(message ?? 'Đã xảy ra lỗi không xác định.');
    appAlert.mode = 'alert';
    appAlert.type = type;
    appAlert.isOpen = true;
};

export const showAppConfirm = (message, type = 'warning') => {
    appAlert.message = String(message ?? 'Bạn có chắc chắn muốn tiếp tục?');
    appAlert.mode = 'confirm';
    appAlert.type = type;
    appAlert.isOpen = true;

    return new Promise((resolve) => {
        confirmResolver = resolve;
    });
};

export const showAppPrompt = (
    message,
    initialValue = '',
    type = 'warning'
) => {
    appAlert.message = String(message ?? 'Nhập nội dung');
    appAlert.mode = 'prompt';
    appAlert.type = type;
    appAlert.inputValue = String(initialValue ?? '');
    appAlert.isOpen = true;

    return new Promise((resolve) => {
        confirmResolver = resolve;
    });
};

export const closeAppAlert = () => {
    appAlert.isOpen = false;
    confirmResolver?.(appAlert.mode === 'prompt' ? null : false);
    confirmResolver = null;
};

export const confirmAppAlert = () => {
    appAlert.isOpen = false;
    confirmResolver?.(
        appAlert.mode === 'prompt'
            ? appAlert.inputValue
            : true
    );
    confirmResolver = null;
};
