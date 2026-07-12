<script setup>
import { computed, useAttrs } from 'vue';
import ResponsiveSelect from './ResponsiveSelect.vue';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: [String, Number, null],
        default: null,
    },

    options: {
        type: [Array, Object],
        default: () => [],
    },

    placeholder: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue']);

const attrs = useAttrs();

const normalized = computed(() => {
    if (Array.isArray(props.options)) {
        return props.options.map((option) => {
            if (
                typeof option === 'object' &&
                option !== null &&
                !Array.isArray(option)
            ) {
                return {
                    ...option,
                    value: option.value,
                    label:
                        option.label ??
                        option.value ??
                        '',
                };
            }

            return {
                value: option,
                label: option,
            };
        });
    }

    return Object.entries(props.options ?? {}).map(
        ([value, label]) => ({
            value,
            label,
        })
    );
});

const valuesMatch = (left, right) => {
    return String(left ?? '') === String(right ?? '');
};

const isDisabled = computed(() => {
    if (
        !Object.prototype.hasOwnProperty.call(
            attrs,
            'disabled'
        )
    ) {
        return false;
    }

    return (
        attrs.disabled !== false &&
        attrs.disabled !== null &&
        attrs.disabled !== undefined
    );
});

const ariaLabel = computed(() => {
    return String(
        attrs['aria-label'] ??
        props.placeholder ??
        'Chọn một giá trị'
    );
});

/*
 * Nhận qua attribute:
 *
 * placement="auto"
 * placement="top"
 * placement="bottom"
 *
 * Không cần thêm vào defineProps của SelectInput.
 */
const placement = computed(() => {
    const value = String(
        attrs.placement ?? 'auto'
    ).toLowerCase();

    return ['auto', 'top', 'bottom'].includes(value)
        ? value
        : 'auto';
});

const forwardedAttrs = computed(() => {
    const result = {
        ...attrs,
    };

    delete result.disabled;
    delete result['aria-label'];
    delete result.placement;

    return result;
});

const updateValue = (value) => {
    const matchedOption = normalized.value.find(
        (option) =>
            valuesMatch(option.value, value)
    );

    emit(
        'update:modelValue',
        matchedOption
            ? matchedOption.value
            : value
    );
};
</script>

<template>
    <ResponsiveSelect
        :model-value="modelValue"
        :options="normalized"
        :placeholder="placeholder"
        :aria-label="ariaLabel"
        :disabled="isDisabled"
        :placement="placement"
        v-bind="forwardedAttrs"
        @update:model-value="updateValue"
    />
</template>