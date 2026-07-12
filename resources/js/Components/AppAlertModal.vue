<script setup>
import {
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

import {
    appAlert,
    closeAppAlert,
    confirmAppAlert,
} from '@/composables/useAppAlert';

const closeButton = ref(null);
const promptInput = ref(null);
const modal = ref(null);
let previousFocus = null;
const isConfirm = () => appAlert.mode === 'confirm';
const isPrompt = () => appAlert.mode === 'prompt';

const getFocusableElements = () => {
    return [...(modal.value?.querySelectorAll(
        'button:not([disabled]), input:not([disabled]), [href], select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    ) || [])];
};

const onKeydown = (event) => {
    if (!appAlert.isOpen) {
        return;
    }

    if (event.key === 'Escape') {
        closeAppAlert();
        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const focusableElements = getFocusableElements();
    if (!focusableElements.length) {
        event.preventDefault();
        return;
    }

    const firstElement = focusableElements[0];
    const lastElement = focusableElements.at(-1);

    if (event.shiftKey && document.activeElement === firstElement) {
        event.preventDefault();
        lastElement.focus();
    } else if (!event.shiftKey && document.activeElement === lastElement) {
        event.preventDefault();
        firstElement.focus();
    }
};

watch(
    () => appAlert.isOpen,
    async (isOpen) => {
        if (isOpen) {
            previousFocus = document.activeElement;
            document.querySelector('#app')?.setAttribute('inert', '');
            await nextTick();
            if (isPrompt()) {
                promptInput.value?.focus();
                promptInput.value?.select();
            } else {
                closeButton.value?.focus();
            }
            return;
        }

        previousFocus?.focus?.();
        previousFocus = null;
        document.querySelector('#app')?.removeAttribute('inert');
    }
);

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.querySelector('#app')?.removeAttribute('inert');
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="appAlert.isOpen"
            class="app-alert-backdrop"
            @click.self="closeAppAlert"
        >
            <section
                ref="modal"
                class="app-alert-modal"
                :class="`is-${appAlert.type}`"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="app-alert-title"
                aria-describedby="app-alert-message"
            >
                <div class="app-alert-modal__icon" aria-hidden="true">
                    <i :class="isConfirm() || isPrompt() ? 'fas fa-circle-question' : 'fas fa-circle-exclamation'"></i>
                </div>

                <div class="app-alert-modal__content">
                    <h2 id="app-alert-title">
                        MyApp (Ic_go-wf) :
                    </h2>

                    <p id="app-alert-message">
                        {{ appAlert.message }}
                    </p>

                    <input
                        v-if="isPrompt()"
                        ref="promptInput"
                        v-model="appAlert.inputValue"
                        type="text"
                        class="form-control app-alert-modal__input"
                        aria-label="Nội dung mới"
                        @keyup.enter="confirmAppAlert"
                    >
                </div>

                <button
                    ref="closeButton"
                    type="button"
                    class="app-alert-modal__close"
                    aria-label="Đóng thông báo"
                    @click="closeAppAlert"
                >
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>

                <div class="app-alert-modal__actions">
                    <button
                        v-if="isConfirm() || isPrompt()"
                        type="button"
                        class="btn btn-outline-secondary app-alert-modal__cancel"
                        @click="closeAppAlert"
                    >
                        Hủy
                    </button>
                    <button
                        type="button"
                        class="btn app-alert-modal__confirm"
                        :class="isConfirm() ? 'btn-danger' : 'btn-primary'"
                        @click="isConfirm() || isPrompt() ? confirmAppAlert() : closeAppAlert()"
                    >
                        {{ isConfirm() || isPrompt() ? 'Xác nhận' : 'Đã hiểu' }}
                    </button>
                </div>
            </section>
        </div>
    </Teleport>
</template>

<style scoped>
.app-alert-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1200;

    display: grid;
    place-items: center;

    padding: 20px;

    background: rgba(9, 30, 66, 0.52);
}

.app-alert-modal {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;

    gap: 14px;
    width: min(100%, 460px);
    padding: 22px;

    color: var(--app-text);

    border: 1px solid var(--app-border);
    border-top: 4px solid #c9372c;
    border-radius: 16px;

    background: var(--app-surface);

    box-shadow: 0 18px 44px rgba(9, 30, 66, 0.28);
}

.app-alert-modal__icon {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;

    color: #c9372c;
    font-size: 1.35rem;

    border-radius: 50%;

    background: #fff1f1;
}

.app-alert-modal.is-success {
    border-top-color: #198754;
}

.app-alert-modal.is-success .app-alert-modal__icon {
    color: #198754;

    background: #edf9f2;
}

.app-alert-modal.is-warning {
    border-top-color: #b7791f;
}

.app-alert-modal.is-warning .app-alert-modal__icon {
    color: #9a6700;

    background: #fff8e8;
}

.app-alert-modal__content {
    min-width: 0;
}

.app-alert-modal__content h2 {
    margin: 0 0 6px;

    font-size: 1rem;
    font-weight: 800;
    line-height: 1.35;
}

.app-alert-modal__content p {
    margin: 0;

    color: var(--app-text-muted);

    font-size: 0.9rem;
    line-height: 1.55;
    overflow-wrap: anywhere;
}

.app-alert-modal__input {
    margin-top: 12px;
}

.app-alert-modal__close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    padding: 0;

    color: var(--app-text-muted);

    border: 1px solid var(--app-border);
    border-radius: 50%;

    background: transparent;
}

.app-alert-modal__actions {
    grid-column: 2 / -1;

    display: flex;
    justify-content: flex-end;

    gap: 8px;
}

.app-alert-modal__confirm {
    min-width: 112px;
}

[data-theme='dark'] .app-alert-modal__icon {
    color: #ffaaa5;

    background: rgba(201, 55, 44, 0.18);
}

[data-theme='dark'] .app-alert-modal.is-success .app-alert-modal__icon {
    color: #8be0b4;

    background: rgba(25, 135, 84, 0.18);
}

[data-theme='dark'] .app-alert-modal.is-warning .app-alert-modal__icon {
    color: #f7cf7c;

    background: rgba(183, 121, 31, 0.18);
}

@media (max-width: 575.98px) {
    .app-alert-backdrop {
        align-items: end;

        padding: 12px;
    }

    .app-alert-modal {
        grid-template-columns: auto minmax(0, 1fr);

        gap: 12px;
        width: 100%;
        padding: 18px 16px calc(18px + env(safe-area-inset-bottom));

        border-radius: 16px;
    }

    .app-alert-modal__close {
        display: none;
    }

    .app-alert-modal__actions {
        grid-column: 1 / -1;
    }

    .app-alert-modal__confirm {
        width: 100%;
        min-height: 44px;
    }

    .app-alert-modal__cancel {
        width: 100%;
        min-height: 44px;
    }
}
</style>
