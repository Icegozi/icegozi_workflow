<script setup>
import { onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    title: { type: String, default: '' },
    maxWidth: { type: String, default: '500px' },
    align: { type: String, default: 'center' }, // 'center' | 'top'
    headerClass: { type: String, default: '' },
});

const emit = defineEmits(['close']);
const close = () => emit('close');

// Đóng bằng phím Esc
const onKey = (e) => { if (e.key === 'Escape') close(); };
onMounted(() => document.addEventListener('keydown', onKey));
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <div class="modal-backdrop-custom" :class="`is-${align}`" @click.self="close">
        <div class="modal-card" :style="{ maxWidth }">
            <!-- Header: dùng slot 'header' nếu cần tuỳ biến, ngược lại hiện title -->
            <div v-if="title || $slots.header" class="modal-card__header" :class="headerClass">
                <slot name="header">
                    <h6 class="modal-card__title">{{ title }}</h6>
                </slot>
                <button type="button" class="modal-card__close" @click="close" aria-label="Đóng">
                    <span>&times;</span>
                </button>
            </div>

            <!-- Nội dung (form…) truyền qua slot mặc định -->
            <div class="modal-card__body">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.modal-backdrop-custom {
    position: fixed;
    inset: 0;
    background: rgba(9, 30, 66, 0.52);
    display: flex;
    justify-content: center;
    z-index: 1060;
    padding: 30px 12px;
}

.modal-backdrop-custom.is-center { align-items: center; }
.modal-backdrop-custom.is-top { align-items: flex-start; }

.modal-card {
    width: 100%;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 60px);
    background: var(--app-surface);
    color: var(--app-text);
    border: 1px solid var(--app-border);
    border-radius: 16px;
    box-shadow: 0 18px 44px rgba(9, 30, 66, 0.28);
    overflow: hidden;
}

.modal-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 14px 18px;
    border-bottom: 1px solid var(--app-border);

    background: linear-gradient(
        135deg,
        rgba(102, 51, 0, 0.1),
        rgba(127, 127, 127, 0.03)
    );
}

.modal-card__title {
    margin: 0;
    font-weight: 700;
    font-size: 1rem;
    letter-spacing: 0.1px;
}

.modal-card__close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    padding: 0;

    color: var(--app-text-muted);
    font-size: 1.25rem;
    line-height: 1;

    border: 1px solid var(--app-border);
    border-radius: 50%;

    background: var(--app-surface);
    cursor: pointer;
}

.modal-card__close:hover {
    color: var(--app-accent);
    border-color: var(--app-accent);
    background: rgba(102, 51, 0, 0.07);
}

.modal-card__body {
    padding: 20px;
    overflow-y: auto;
}

:deep(.modal-form) {
    display: flex;
    flex-direction: column;

    gap: 18px;
}

:deep(.modal-form .form-group) {
    margin: 0;
}

:deep(.modal-form label) {
    margin-bottom: 7px;

    color: var(--app-text-muted);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.45px;

    text-transform: uppercase;
}

:deep(.modal-form__actions) {
    display: flex;
    justify-content: flex-end;

    gap: 8px;
    padding-top: 2px;
}

@media (max-width: 575.98px) {
    .modal-backdrop-custom {
        align-items: flex-end;
        padding: 0;
    }

    .modal-card {
        max-height: 90dvh;

        border-radius: 18px 18px 0 0;
    }

    .modal-card__header {
        padding: 12px 14px;
    }

    .modal-card__body {
        padding: 16px 14px calc(18px + env(safe-area-inset-bottom));
    }

    :deep(.modal-form__actions) {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    :deep(.modal-form__actions .btn) {
        width: 100%;
        min-height: 44px;
        margin: 0 !important;
    }
}
</style>
