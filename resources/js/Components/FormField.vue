<script setup>
// Khối trường: label + nội dung (slot) + thông báo lỗi.
defineProps({
    label: { type: String, default: null },
    forId: { type: String, default: null },
    error: { type: String, default: null },
    labelClass: { type: String, default: 'small font-weight-bold' },
});
</script>

<template>
    <div class="form-group">
        <label v-if="label" :for="forId" :class="labelClass">{{ label }}</label>
        <slot />
        <div v-if="error" class="invalid-feedback small d-block">{{ error }}</div>
    </div>
</template>
