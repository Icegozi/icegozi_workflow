<script setup>
// Combobox dùng chung. options: mảng [{value,label}] hoặc object {value:label}.
import { computed } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    options: { type: [Array, Object], default: () => [] },
    placeholder: { type: String, default: null },
});

defineEmits(['update:modelValue']);

const normalized = computed(() => {
    if (Array.isArray(props.options)) {
        return props.options.map((o) =>
            typeof o === 'object' ? { value: o.value, label: o.label } : { value: o, label: o }
        );
    }
    return Object.entries(props.options).map(([value, label]) => ({ value, label }));
});
</script>

<template>
    <select
        class="form-control"
        :value="modelValue"
        v-bind="$attrs"
        @change="$emit('update:modelValue', $event.target.value)"
    >
        <option v-if="placeholder !== null" value="" disabled>{{ placeholder }}</option>
        <option v-for="opt in normalized" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
    </select>
</template>
