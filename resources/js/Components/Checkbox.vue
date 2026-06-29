<script setup>
// Ô tích chọn dùng chung (Bootstrap form-check), hỗ trợ v-model (boolean).
import { useId } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    // Để undefined khi không dùng v-model => checkbox uncontrolled (cho form POST native).
    modelValue: { type: Boolean, default: undefined },
    label: { type: String, default: '' },
    id: { type: String, default: null },
    // bare = chỉ render ô tích, không bọc form-check/label (cho checkbox inline).
    bare: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

// Tự sinh id nếu không truyền, để liên kết input ↔ label.
const generatedId = useId();
const inputId = props.id || generatedId;
</script>

<template>
    <!-- Chỉ ô tích, không nhãn -->
    <input
        v-if="bare"
        type="checkbox"
        class="form-check-input"
        :checked="modelValue"
        v-bind="$attrs"
        @change="$emit('update:modelValue', $event.target.checked)"
    >

    <!-- Ô tích + nhãn (Bootstrap form-check) -->
    <div v-else class="form-check">
        <input
            type="checkbox"
            class="form-check-input"
            :id="inputId"
            :checked="modelValue"
            v-bind="$attrs"
            @change="$emit('update:modelValue', $event.target.checked)"
        >
        <label class="form-check-label" :for="inputId">
            <slot>{{ label }}</slot>
        </label>
    </div>
</template>
