<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useId,
    watch,
} from 'vue';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: [String, Number, null],
        default: null,
    },

    options: {
        type: Array,
        required: true,
    },

    ariaLabel: {
        type: String,
        required: true,
    },

    placeholder: {
        type: String,
        default: null,
    },

    disabled: {
        type: Boolean,
        default: false,
    },

    placement: {
        type: String,
        default: 'auto',
        validator: (value) =>
            ['auto', 'top', 'bottom'].includes(value),
    },
});

const emit = defineEmits(['update:modelValue']);

const root = ref(null);
const trigger = ref(null);
const menu = ref(null);

const open = ref(false);
const resolvedPlacement = ref('bottom');

const menuPosition = ref({
    top: 0,
    left: 0,
    width: 0,
    maxHeight: 240,
});

const listboxId = `responsive-select-${useId()}`;

const MENU_GAP = 4;
const VIEWPORT_PADDING = 8;
const MAX_MENU_HEIGHT = 240;
const MIN_USABLE_HEIGHT = 80;

let animationFrameId = null;

const valuesMatch = (left, right) => {
    return String(left ?? '') === String(right ?? '');
};

const selectedIndex = computed(() => {
    return props.options.findIndex((option) =>
        valuesMatch(
            option.value,
            props.modelValue
        )
    );
});

const selectedOption = computed(() => {
    if (selectedIndex.value < 0) {
        return null;
    }

    return (
        props.options[selectedIndex.value] ??
        null
    );
});

const displayedLabel = computed(() => {
    return (
        selectedOption.value?.label ??
        props.placeholder ??
        ''
    );
});

const menuStyle = computed(() => ({
    position: 'fixed',
    top: `${menuPosition.value.top}px`,
    left: `${menuPosition.value.left}px`,
    width: `${menuPosition.value.width}px`,
    maxHeight: `${menuPosition.value.maxHeight}px`,
}));

const getViewport = () => {
    const viewport = window.visualViewport;

    return {
        top: viewport?.offsetTop ?? 0,
        left: viewport?.offsetLeft ?? 0,
        width:
            viewport?.width ??
            window.innerWidth,
        height:
            viewport?.height ??
            window.innerHeight,
    };
};

const calculateMenuPosition = async () => {
    if (
        !open.value ||
        !trigger.value ||
        !menu.value
    ) {
        return;
    }

    await nextTick();

    const triggerRect =
        trigger.value.getBoundingClientRect();

    const viewport = getViewport();

    const viewportBottom =
        viewport.top + viewport.height;

    const viewportRight =
        viewport.left + viewport.width;

    const availableBelow = Math.max(
        0,
        viewportBottom -
            triggerRect.bottom -
            MENU_GAP -
            VIEWPORT_PADDING
    );

    const availableAbove = Math.max(
        0,
        triggerRect.top -
            viewport.top -
            MENU_GAP -
            VIEWPORT_PADDING
    );

    const desiredHeight = Math.min(
        menu.value.scrollHeight ||
            MAX_MENU_HEIGHT,
        MAX_MENU_HEIGHT
    );

    /*
     * Chọn hướng.
     */
    if (props.placement === 'top') {
        resolvedPlacement.value = 'top';
    } else if (
        props.placement === 'bottom'
    ) {
        resolvedPlacement.value = 'bottom';
    } else if (
        availableBelow >= desiredHeight
    ) {
        resolvedPlacement.value = 'bottom';
    } else if (
        availableAbove >= desiredHeight
    ) {
        resolvedPlacement.value = 'top';
    } else {
        resolvedPlacement.value =
            availableAbove > availableBelow
                ? 'top'
                : 'bottom';
    }

    const availableHeight =
        resolvedPlacement.value === 'top'
            ? availableAbove
            : availableBelow;

    const maxHeight = Math.max(
        Math.min(
            MIN_USABLE_HEIGHT,
            Math.max(
                availableAbove,
                availableBelow
            )
        ),
        Math.min(
            MAX_MENU_HEIGHT,
            Math.floor(availableHeight)
        )
    );

    /*
     * Menu dùng fixed nên cần lấy kích thước
     * sau khi max-height được cập nhật.
     */
    menuPosition.value.maxHeight =
        maxHeight;

    await nextTick();

    const actualMenuHeight = Math.min(
        menu.value.scrollHeight,
        maxHeight
    );

    let top;

    if (
        resolvedPlacement.value === 'top'
    ) {
        top =
            triggerRect.top -
            actualMenuHeight -
            MENU_GAP;
    } else {
        top =
            triggerRect.bottom +
            MENU_GAP;
    }

    /*
     * Không cho menu vượt viewport theo chiều dọc.
     */
    top = Math.max(
        viewport.top + VIEWPORT_PADDING,
        Math.min(
            top,
            viewportBottom -
                actualMenuHeight -
                VIEWPORT_PADDING
        )
    );

    let left = triggerRect.left;
    let width = triggerRect.width;

    /*
     * Không cho menu vượt viewport theo chiều ngang.
     */
    if (
        left + width >
        viewportRight - VIEWPORT_PADDING
    ) {
        left =
            viewportRight -
            width -
            VIEWPORT_PADDING;
    }

    left = Math.max(
        viewport.left + VIEWPORT_PADDING,
        left
    );

    width = Math.min(
        width,
        viewport.width -
            VIEWPORT_PADDING * 2
    );

    menuPosition.value = {
        top,
        left,
        width,
        maxHeight,
    };
};

const schedulePositionUpdate = () => {
    if (!open.value) {
        return;
    }

    if (animationFrameId !== null) {
        cancelAnimationFrame(
            animationFrameId
        );
    }

    animationFrameId =
        requestAnimationFrame(() => {
            animationFrameId = null;
            calculateMenuPosition();
        });
};

const getOptionButtons = () => {
    if (!menu.value) {
        return [];
    }

    return Array.from(
        menu.value.querySelectorAll(
            '.responsive-select__option:not(:disabled)'
        )
    );
};

const focusOption = async (index) => {
    await nextTick();

    const buttons = getOptionButtons();

    if (!buttons.length) {
        return;
    }

    const option =
        props.options[index] ??
        props.options[0];

    const button =
        buttons.find((element) =>
            valuesMatch(
                element.dataset.value,
                option?.value
            )
        ) ?? buttons[0];

    button?.focus();
};

const showOptions = async () => {
    if (
        props.disabled ||
        props.options.length === 0
    ) {
        return;
    }

    open.value = true;

    await nextTick();
    await calculateMenuPosition();

    focusOption(
        Math.max(selectedIndex.value, 0)
    );
};

const hideOptions = ({
    restoreFocus = false,
} = {}) => {
    open.value = false;

    if (restoreFocus) {
        nextTick(() => {
            trigger.value?.focus();
        });
    }
};

const toggleOptions = () => {
    if (open.value) {
        hideOptions();
    } else {
        showOptions();
    }
};

const selectOption = (option) => {
    if (option.disabled) {
        return;
    }

    emit(
        'update:modelValue',
        option.value
    );

    hideOptions({
        restoreFocus: true,
    });
};

const onNativeChange = (event) => {
    const value = event.target.value;

    const matchedOption =
        props.options.find((option) =>
            valuesMatch(
                option.value,
                value
            )
        );

    emit(
        'update:modelValue',
        matchedOption
            ? matchedOption.value
            : value
    );
};

const onTriggerKeydown = (event) => {
    if (
        [
            'ArrowDown',
            'ArrowUp',
            'Enter',
            ' ',
        ].includes(event.key)
    ) {
        event.preventDefault();
        showOptions();
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();

        hideOptions({
            restoreFocus: true,
        });
    }
};

const findEnabledOptionIndex = (
    currentIndex,
    direction
) => {
    const total = props.options.length;

    if (!total) {
        return null;
    }

    let index = currentIndex;

    for (
        let attempt = 0;
        attempt < total;
        attempt += 1
    ) {
        index =
            (
                index +
                direction +
                total
            ) % total;

        if (
            !props.options[index]?.disabled
        ) {
            return index;
        }
    }

    return null;
};

const onOptionKeydown = (
    event,
    index,
    option
) => {
    let nextIndex = null;

    if (event.key === 'ArrowDown') {
        nextIndex =
            findEnabledOptionIndex(
                index,
                1
            );
    }

    if (event.key === 'ArrowUp') {
        nextIndex =
            findEnabledOptionIndex(
                index,
                -1
            );
    }

    if (event.key === 'Home') {
        nextIndex =
            props.options.findIndex(
                (item) =>
                    !item.disabled
            );
    }

    if (event.key === 'End') {
        for (
            let optionIndex =
                props.options.length - 1;
            optionIndex >= 0;
            optionIndex -= 1
        ) {
            if (
                !props.options[
                    optionIndex
                ]?.disabled
            ) {
                nextIndex = optionIndex;
                break;
            }
        }
    }

    if (
        nextIndex !== null &&
        nextIndex >= 0
    ) {
        event.preventDefault();
        focusOption(nextIndex);
        return;
    }

    if (
        event.key === 'Enter' ||
        event.key === ' '
    ) {
        event.preventDefault();
        selectOption(option);
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();

        hideOptions({
            restoreFocus: true,
        });

        return;
    }

    if (event.key === 'Tab') {
        hideOptions();
    }
};

const onDocumentPointerDown = (event) => {
    if (!open.value) {
        return;
    }

    const clickedRoot =
        root.value?.contains(event.target);

    const clickedMenu =
        menu.value?.contains(event.target);

    if (!clickedRoot && !clickedMenu) {
        hideOptions();
    }
};

watch(
    () => props.placement,
    () => {
        schedulePositionUpdate();
    }
);

watch(
    () => props.options.length,
    () => {
        schedulePositionUpdate();
    }
);

watch(
    () => props.disabled,
    (disabled) => {
        if (disabled) {
            hideOptions();
        }
    }
);

onMounted(() => {
    document.addEventListener(
        'pointerdown',
        onDocumentPointerDown
    );

    window.addEventListener(
        'scroll',
        schedulePositionUpdate,
        true
    );

    window.addEventListener(
        'resize',
        schedulePositionUpdate
    );

    window.visualViewport?.addEventListener(
        'resize',
        schedulePositionUpdate
    );

    window.visualViewport?.addEventListener(
        'scroll',
        schedulePositionUpdate
    );
});

onBeforeUnmount(() => {
    document.removeEventListener(
        'pointerdown',
        onDocumentPointerDown
    );

    window.removeEventListener(
        'scroll',
        schedulePositionUpdate,
        true
    );

    window.removeEventListener(
        'resize',
        schedulePositionUpdate
    );

    window.visualViewport?.removeEventListener(
        'resize',
        schedulePositionUpdate
    );

    window.visualViewport?.removeEventListener(
        'scroll',
        schedulePositionUpdate
    );

    if (animationFrameId !== null) {
        cancelAnimationFrame(
            animationFrameId
        );
    }
});
</script>

<template>
    <div
        ref="root"
        class="responsive-select"
        :class="{
            'is-open': open,
            'is-disabled': disabled,
        }"
    >
        <select
            class="form-control responsive-select__native"
            :value="modelValue ?? ''"
            :aria-label="ariaLabel"
            :disabled="disabled"
            v-bind="$attrs"
            @change="onNativeChange"
        >
            <option
                v-if="placeholder !== null"
                value=""
                disabled
            >
                {{ placeholder }}
            </option>

            <option
                v-for="option in options"
                :key="String(option.value)"
                :value="option.value"
                :disabled="option.disabled"
            >
                {{ option.label }}
            </option>
        </select>

        <button
            ref="trigger"
            type="button"
            class="form-control responsive-select__trigger"
            :aria-label="ariaLabel"
            aria-haspopup="listbox"
            :aria-expanded="open"
            :aria-controls="listboxId"
            :disabled="disabled"
            @click="toggleOptions"
            @keydown="onTriggerKeydown"
        >
            <span
                class="responsive-select__value"
                :class="{
                    'is-placeholder':
                        !selectedOption,
                }"
            >
                {{ displayedLabel }}
            </span>

            <i
                class="fas fa-chevron-down responsive-select__chevron"
                aria-hidden="true"
            ></i>
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                :id="listboxId"
                ref="menu"
                class="responsive-select__menu"
                :class="{
                    'responsive-select__menu--top':
                        resolvedPlacement ===
                        'top',
                    'responsive-select__menu--bottom':
                        resolvedPlacement ===
                        'bottom',
                }"
                :style="menuStyle"
                role="listbox"
                :aria-label="ariaLabel"
            >
                <button
                    v-for="(option, index) in options"
                    :key="String(option.value)"
                    type="button"
                    class="responsive-select__option"
                    :class="{
                        'is-selected':
                            valuesMatch(
                                option.value,
                                modelValue
                            ),
                    }"
                    :data-value="
                        String(option.value)
                    "
                    :disabled="option.disabled"
                    role="option"
                    :aria-selected="
                        valuesMatch(
                            option.value,
                            modelValue
                        )
                    "
                    @click="
                        selectOption(option)
                    "
                    @keydown="
                        onOptionKeydown(
                            $event,
                            index,
                            option
                        )
                    "
                >
                    <span
                        class="responsive-select__option-label"
                    >
                        {{ option.label }}
                    </span>

                    <i
                        v-if="
                            valuesMatch(
                                option.value,
                                modelValue
                            )
                        "
                        class="fas fa-check responsive-select__check"
                        aria-hidden="true"
                    ></i>
                </button>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.responsive-select {
    position: relative;
    display: block;
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

.responsive-select__native {
    width: 100%;
    min-width: 0;
    max-width: 100%;

    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.responsive-select__trigger {
    display: none;
}

.responsive-select__menu {
    display: none;
}

@media (max-width: 767.98px) {
    .responsive-select__native {
        display: none;
    }

    .responsive-select__trigger {
        display: flex;
        align-items: center;
        justify-content: space-between;

        width: 100%;
        min-width: 0;
        max-width: 100%;

        color: var(--app-text);
        text-align: left;
        background: var(--app-surface);
    }

    .responsive-select__value {
        min-width: 0;
        flex: 1 1 auto;

        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .responsive-select__value.is-placeholder {
        color: var(--app-text-muted);
    }

    .responsive-select__chevron {
        flex: 0 0 auto;
        margin-left: 10px;

        color: var(--app-text-muted);
        font-size: 0.7rem;

        transition: transform 0.15s ease;
    }

    .responsive-select__trigger[aria-expanded='true']
        .responsive-select__chevron {
        transform: rotate(180deg);
    }
}

/*
 * Menu được Teleport ra body.
 * Scoped style vẫn áp dụng vì Vue gắn scope attribute.
 */
.responsive-select__menu {
    z-index: 9999;

    display: block;

    min-width: 0;

    padding: 4px;

    overflow-x: hidden;
    overflow-y: auto;

    border: 1px solid var(--app-border);
    border-radius: 6px;

    background: var(--app-surface);

    box-shadow:
        0 8px 24px
        rgba(0, 0, 0, 0.16);

    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
}

.responsive-select__option {
    display: flex;
    align-items: center;
    justify-content: space-between;

    width: 100%;
    min-width: 0;

    gap: 8px;
    padding: 9px 10px;

    overflow: hidden;

    border: 0;
    border-radius: 4px;

    color: var(--app-text);
    text-align: left;

    background: transparent;
}

.responsive-select__option-label {
    min-width: 0;
    flex: 1 1 auto;

    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.responsive-select__check {
    flex: 0 0 auto;
    font-size: 0.75rem;
}

.responsive-select__option:hover,
.responsive-select__option:focus,
.responsive-select__option.is-selected {
    color: #fff;
    outline: 0;
    background: #663300;
}

.responsive-select__option:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}
</style>
