<script setup>
// Ô nhập liệu dùng chung (input-group + icon tuỳ chọn), hỗ trợ v-model.
defineOptions({ inheritAttrs: false });

defineProps({
    // Để undefined khi không dùng v-model => input hoạt động uncontrolled (cho form POST native).
    modelValue: { type: [String, Number], default: undefined },
    type: { type: String, default: 'text' },
    icon: { type: String, default: null },
    groupClass: { type: String, default: 'mb-3' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="input-group" :class="groupClass">
        <input
            :type="type"
            class="form-control"
            :value="modelValue"
            v-bind="$attrs"
            @input="$emit('update:modelValue', $event.target.value)"
        >
        <div v-if="icon" class="input-group-append">
            <div class="input-group-text h-100"><span :class="icon"></span></div>
        </div>
    </div>
</template>
