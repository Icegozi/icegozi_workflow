<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, required: true },
    ariaLabel: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const root = ref(null);
const trigger = ref(null);
const optionButtons = ref([]);
const open = ref(false);
const listboxId = `responsive-select-${useId()}`;

const valuesMatch = (left, right) => String(left ?? '') === String(right ?? '');
const selectedIndex = computed(() => props.options.findIndex((option) => valuesMatch(option.value, props.modelValue)));
const selectedOption = computed(() => props.options[selectedIndex.value] ?? props.options[0] ?? null);

const focusOption = async (index) => {
    await nextTick();
    optionButtons.value[index]?.focus();
};

const showOptions = () => {
    if (props.disabled) return;
    open.value = true;
    focusOption(Math.max(selectedIndex.value, 0));
};

const hideOptions = ({ restoreFocus = false } = {}) => {
    open.value = false;
    if (restoreFocus) nextTick(() => trigger.value?.focus());
};

const toggleOptions = () => {
    if (open.value) {
        hideOptions();
        return;
    }
    showOptions();
};

const selectOption = (option) => {
    emit('update:modelValue', option.value);
    hideOptions({ restoreFocus: true });
};

const onTriggerKeydown = (event) => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        showOptions();
    }
};

const onOptionKeydown = (event, index) => {
    let nextIndex = null;

    if (event.key === 'ArrowDown') nextIndex = (index + 1) % props.options.length;
    if (event.key === 'ArrowUp') nextIndex = (index - 1 + props.options.length) % props.options.length;
    if (event.key === 'Home') nextIndex = 0;
    if (event.key === 'End') nextIndex = props.options.length - 1;

    if (nextIndex !== null) {
        event.preventDefault();
        focusOption(nextIndex);
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        hideOptions({ restoreFocus: true });
    }
};

const onDocumentPointerDown = (event) => {
    if (open.value && !root.value?.contains(event.target)) hideOptions();
};

onMounted(() => document.addEventListener('pointerdown', onDocumentPointerDown));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onDocumentPointerDown));
</script>

<template>
    <div ref="root" class="responsive-select">
        <select
            class="form-control form-control-sm responsive-select__native"
            :value="modelValue"
            :aria-label="ariaLabel"
            :disabled="disabled"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <option v-for="option in options" :key="String(option.value)" :value="option.value">
                {{ option.label }}
            </option>
        </select>

        <button
            ref="trigger"
            type="button"
            class="form-control form-control-sm responsive-select__trigger"
            :aria-label="ariaLabel"
            aria-haspopup="listbox"
            :aria-expanded="open"
            :aria-controls="listboxId"
            :disabled="disabled"
            @click="toggleOptions"
            @keydown="onTriggerKeydown"
        >
            <span class="responsive-select__value">{{ selectedOption?.label }}</span>
            <i class="fas fa-chevron-down responsive-select__chevron" aria-hidden="true"></i>
        </button>

        <div v-if="open" :id="listboxId" class="responsive-select__menu" role="listbox" :aria-label="ariaLabel">
            <button
                v-for="(option, index) in options"
                :key="String(option.value)"
                :ref="(element) => { if (element) optionButtons[index] = element; }"
                type="button"
                class="responsive-select__option"
                :class="{ 'is-selected': valuesMatch(option.value, modelValue) }"
                role="option"
                :aria-selected="valuesMatch(option.value, modelValue)"
                @click="selectOption(option)"
                @keydown="onOptionKeydown($event, index)"
            >
                {{ option.label }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.responsive-select {
    position: relative;
    display: inline-block;
    min-width: 0;
}

.responsive-select__native {
    width: auto;
}

.responsive-select__trigger,
.responsive-select__menu {
    display: none;
}

@media (max-width: 767.98px) {
    .responsive-select {
        display: block;
    }

    .responsive-select__native {
        display: none;
    }

    .responsive-select__trigger {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        min-width: 0;
        color: var(--app-text);
        text-align: left;
        background: var(--app-surface);
    }

    .responsive-select__value {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .responsive-select__chevron {
        flex: 0 0 auto;
        margin-left: 10px;
        color: var(--app-text-muted);
        font-size: 0.7rem;
        transition: transform 0.15s ease;
    }

    .responsive-select__trigger[aria-expanded="true"] .responsive-select__chevron {
        transform: rotate(180deg);
    }

    .responsive-select__menu {
        position: absolute;
        top: calc(100% + 4px);
        right: 0;
        left: 0;
        z-index: 1060;
        display: block;
        width: 100%;
        min-width: 0;
        max-width: 100%;
        max-height: min(240px, calc(100dvh - 120px));
        padding: 4px;
        overflow-x: hidden;
        overflow-y: auto;
        border: 1px solid var(--app-border);
        border-radius: 6px;
        background: var(--app-surface);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.16);
    }

    .responsive-select__option {
        display: block;
        width: 100%;
        min-width: 0;
        padding: 9px 10px;
        overflow: hidden;
        border: 0;
        border-radius: 4px;
        color: var(--app-text);
        text-align: left;
        text-overflow: ellipsis;
        white-space: nowrap;
        background: transparent;
    }

    .responsive-select__option:hover,
    .responsive-select__option:focus,
    .responsive-select__option.is-selected {
        color: #fff;
        outline: 0;
        background: var(--app-accent);
    }
}
</style>
