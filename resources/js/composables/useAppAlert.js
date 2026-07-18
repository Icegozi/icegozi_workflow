import { reactive } from 'vue';

export const appAlert = reactive({
    isOpen: false,
    message: '',
    mode: 'alert',
    type: 'danger',
    inputValue: '',
    primaryLabel: 'Xác nhận',
    secondaryLabel: 'Hủy',
});

const alertQueue = [];
let confirmResolver = null;
let isDisplaying = false;

const showNextAlert = () => {
    if (isDisplaying || !alertQueue.length) {
        return;
    }

    const nextAlert = alertQueue.shift();
    isDisplaying = true;
    confirmResolver = nextAlert.resolve;
    appAlert.message = nextAlert.message;
    appAlert.mode = nextAlert.mode;
    appAlert.type = nextAlert.type;
    appAlert.inputValue = nextAlert.inputValue;
    appAlert.primaryLabel = nextAlert.primaryLabel || 'Xác nhận';
    appAlert.secondaryLabel = nextAlert.secondaryLabel || 'Hủy';
    appAlert.isOpen = true;
};

const queueAlert = (alert) => {
    alertQueue.push(alert);
    showNextAlert();
};

const closeCurrentAlert = (value) => {
    const resolver = confirmResolver;

    confirmResolver = null;
    isDisplaying = false;
    appAlert.isOpen = false;
    resolver?.(value);

    window.setTimeout(showNextAlert, 0);
};

export const showAppAlert = (message, type = 'danger') => {
    queueAlert({
        message: String(message ?? 'Đã xảy ra lỗi không xác định.'),
        mode: 'alert',
        type,
        inputValue: '',
        resolve: null,
    });
};

export const showAppConfirm = (message, type = 'warning') => {
    return new Promise((resolve) => {
        queueAlert({
            message: String(message ?? 'Bạn có chắc chắn muốn tiếp tục?'),
            mode: 'confirm',
            type,
            inputValue: '',
            resolve,
        });
    });
};

export const showAppPrompt = (
    message,
    initialValue = '',
    type = 'warning'
) => {
    return new Promise((resolve) => {
        queueAlert({
            message: String(message ?? 'Nhập nội dung'),
            mode: 'prompt',
            type,
            inputValue: String(initialValue ?? ''),
            resolve,
        });
    });
};

export const showAppChoice = (message, options = {}) => {
    return new Promise((resolve) => {
        queueAlert({
            message: String(message ?? 'Chọn cách xử lý.'),
            mode: 'choice',
            type: options.type || 'warning',
            inputValue: '',
            primaryLabel: options.primaryLabel || 'Xác nhận',
            secondaryLabel: options.secondaryLabel || 'Hủy',
            resolve,
        });
    });
};

export const closeAppAlert = () => {
    if (appAlert.mode === 'choice') {
        closeCurrentAlert('dismissed');
        return;
    }
    closeCurrentAlert(appAlert.mode === 'prompt' ? null : false);
};

export const confirmAppAlert = () => {
    closeCurrentAlert(appAlert.mode === 'choice' ? 'primary' : (appAlert.mode === 'prompt' ? appAlert.inputValue : true));
};
