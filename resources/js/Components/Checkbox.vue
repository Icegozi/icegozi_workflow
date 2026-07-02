<script setup>
// Ô chọn boolean dùng chung, hỗ trợ v-model.
//  - bare      : chỉ render ô tích trần (dùng cho checklist / inline).
//  - mặc định  : công tắc (switch) hiện đại + nhãn (dùng cho thiết lập boolean).
import { computed, useAttrs, useId } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: Boolean, default: undefined },
    label: { type: String, default: '' },
    id: { type: String, default: null },
    bare: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const generatedId = useId();
const inputId = props.id || generatedId;

// Tách class (đưa lên phần tử gốc) khỏi các attr còn lại (đưa vào input).
const attrs = useAttrs();
const rootClass = computed(() => attrs.class);
const inputAttrs = computed(() => {
    const { class: _c, ...rest } = attrs;

    return rest;
});
</script>

<template>
    <!-- Chỉ ô tích trần (checklist / inline) -->
    <input
        v-if="bare"
        type="checkbox"
        class="form-check-input"
        :checked="modelValue"
        v-bind="$attrs"
        @change="$emit('update:modelValue', $event.target.checked)"
    >

    <!-- Công tắc hiện đại + nhãn -->
    <label v-else class="switch-field" :class="rootClass" :for="inputId">
        <span class="switch">
            <input
                type="checkbox"
                class="switch-input"
                :id="inputId"
                :checked="modelValue"
                v-bind="inputAttrs"
                @change="$emit('update:modelValue', $event.target.checked)"
            >
            <span class="switch-slider"></span>
        </span>
        <span v-if="label || $slots.default" class="switch-label"><slot>{{ label }}</slot></span>
    </label>
</template>

<style scoped>
.switch-field {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 0;
    cursor: pointer;
    user-select: none;
}

.switch {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 22px;
    flex: 0 0 auto;
}

.switch-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.switch-slider {
    position: absolute;
    inset: 0;
    background: #c1c7d0;
    border-radius: 22px;
    transition: background 0.2s ease;
}

.switch-slider::before {
    content: '';
    position: absolute;
    height: 16px;
    width: 16px;
    left: 3px;
    top: 3px;
    background: #fff;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
    transition: transform 0.2s ease;
}

.switch-input:checked + .switch-slider {
    background: var(--app-accent, #663300);
}

.switch-input:checked + .switch-slider::before {
    transform: translateX(18px);
}

.switch-input:focus-visible + .switch-slider {
    box-shadow: 0 0 0 3px rgba(102, 51, 0, 0.35);
}

.switch-input:disabled + .switch-slider {
    opacity: 0.5;
    cursor: not-allowed;
}

.switch-label {
    font-size: 0.9rem;
}
</style>
