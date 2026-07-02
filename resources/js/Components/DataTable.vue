<script setup>
import { computed, useSlots } from 'vue';

const props = defineProps({
    // Mỗi cột: { key, label, width, align: 'left'|'center'|'right', tdClass }
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: 'id' },
    emptyText: { type: String, default: 'Không có dữ liệu.' },
    actionsLabel: { type: String, default: 'Hành động' },
    // Ép hiện/ẩn cột hành động; mặc định theo việc có slot #actions hay không
    showActions: { type: Boolean, default: null },
});

const slots = useSlots();
const hasActions = computed(() =>
    props.showActions === null ? !!slots.actions : props.showActions
);
const totalCols = computed(() => props.columns.length + (hasActions.value ? 1 : 0));
const alignClass = (a) => (a === 'center' ? 'text-center' : a === 'right' ? 'text-right' : '');
</script>

<template>
    <div class="dt-wrap">
        <table class="dt">
            <thead>
                <tr>
                    <th v-for="c in columns" :key="c.key" :class="alignClass(c.align)"
                        :style="c.width ? { width: c.width } : null">{{ c.label }}</th>
                    <th v-if="hasActions" class="text-right">{{ actionsLabel }}</th>
                </tr>
            </thead>
            <tbody>
                <slot name="prepend" />
                <tr v-for="row in rows" :key="row[rowKey]">
                    <td v-for="c in columns" :key="c.key" :class="[alignClass(c.align), c.tdClass]">
                        <slot :name="`cell-${c.key}`" :row="row" :value="row[c.key]">{{ row[c.key] }}</slot>
                    </td>
                    <td v-if="hasActions" class="text-right">
                        <slot name="actions" :row="row" />
                    </td>
                </tr>
                <tr v-if="!rows.length">
                    <td :colspan="totalCols" class="dt-empty">
                        <slot name="empty">{{ emptyText }}</slot>
                    </td>
                </tr>
            </tbody>
        </table>
        <slot name="footer" />
    </div>
</template>

<style scoped>
.dt-wrap {
    width: 100%;
    overflow-x: auto;
}

.dt {
    width: 100%;
    background: var(--app-surface, #fff);
    color: var(--app-text, #212529);
    border: 1px solid var(--app-border, #e4e6ea);
    border-radius: 10px;
    border-collapse: separate;
    border-spacing: 0;
    overflow: hidden;
    font-size: 0.9rem;
}

.dt thead th {
    background: rgba(127, 127, 127, 0.08);
    font-weight: 700;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: var(--app-text, #44546f);
    padding: 10px 12px;
    border-bottom: 2px solid var(--app-accent, #663300);
    white-space: nowrap;
}

.dt tbody td {
    padding: 10px 12px;
    border-top: 1px solid rgba(127, 127, 127, 0.15);
    vertical-align: middle;
}

.dt tbody tr:hover {
    background: rgba(127, 127, 127, 0.06);
}

.dt-empty {
    text-align: center;
    color: #8993a4;
    padding: 24px 12px !important;
}
</style>
