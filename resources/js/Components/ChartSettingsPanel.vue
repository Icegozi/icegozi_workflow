<script setup>
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    settings: { type: Object, required: true },   // object reactive (mutate trực tiếp)
    charts: { type: Array, default: () => [] },    // [{key,label,types:[{value,label}]}]
    showRange: { type: Boolean, default: true },
});

const open = ref(false);
const RANGES = [
    { value: 7, label: '7 ngày' },
    { value: 14, label: '14 ngày' },
    { value: 30, label: '30 ngày' },
    { value: 90, label: '90 ngày' },
];

// Danh sách chart theo thứ tự hiển thị hiện tại
const orderedCharts = computed(() =>
    [...props.charts].sort((a, b) =>
        (props.settings.charts[a.key]?.order ?? 0) - (props.settings.charts[b.key]?.order ?? 0)
    )
);

const move = (index, dir) => {
    const list = orderedCharts.value;
    const target = index + dir;
    if (target < 0 || target >= list.length) return;
    const a = props.settings.charts[list[index].key];
    const b = props.settings.charts[list[target].key];
    const tmp = a.order;
    a.order = b.order;
    b.order = tmp;
};
</script>

<template>
    <span>
        <button class="btn btn-sm btn-outline-secondary" title="Thiết lập biểu đồ" @click="open = true">
            <i class="fas fa-sliders-h"></i>
        </button>

        <Modal v-if="open" title="Thiết lập biểu đồ" max-width="560px" @close="open = false">
            <!-- Khoảng thời gian -->
            <div v-if="showRange" class="mb-3">
                <label class="small font-weight-bold d-block mb-1">Khoảng thời gian</label>
                <div class="btn-group btn-group-sm" role="group">
                    <button v-for="r in RANGES" :key="r.value" type="button" class="btn"
                        :class="settings.range === r.value ? 'btn-dark' : 'btn-outline-secondary'"
                        @click="settings.range = r.value">{{ r.label }}</button>
                </div>
            </div>

            <!-- Kiểu hiển thị -->
            <div class="mb-3">
                <label class="small font-weight-bold d-block mb-2">Kiểu hiển thị</label>
                <div class="d-flex flex-wrap" style="gap:16px;">
                    <Checkbox v-model="settings.legend" label="Chú giải (legend)" />
                    <Checkbox v-model="settings.grid" label="Đường lưới" />
                    <Checkbox v-model="settings.values" label="Hiện số liệu" />
                </div>
            </div>

            <!-- Từng biểu đồ: ẩn/hiện + kiểu + sắp xếp -->
            <label class="small font-weight-bold d-block mb-2">Biểu đồ</label>
            <div class="chart-rows">
                <div v-for="(c, i) in orderedCharts" :key="c.key" class="chart-row">
                    <div class="reorder">
                        <button type="button" class="btn btn-sm btn-light py-0" :disabled="i === 0" @click="move(i, -1)">
                            <i class="fas fa-chevron-up"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-light py-0" :disabled="i === orderedCharts.length - 1" @click="move(i, 1)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <Checkbox v-model="settings.charts[c.key].visible" :label="c.label" class="flex-grow-1" />
                    <select v-if="c.types && c.types.length > 1" class="form-control form-control-sm"
                        style="width:auto;" v-model="settings.charts[c.key].type">
                        <option v-for="t in c.types" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                </div>
            </div>

            <div class="text-right mt-3">
                <button class="btn btn-sm btn-dark" @click="open = false">Xong</button>
            </div>
            <p class="text-muted small mt-2 mb-0"><i class="fas fa-circle-info mr-1"></i>Thiết lập tự lưu theo tài khoản.</p>
        </Modal>
    </span>
</template>

<style scoped>
.chart-rows {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.chart-row {
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid var(--app-border, #e4e6ea);
    border-radius: 8px;
    padding: 6px 10px;
}

.reorder {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.reorder .btn {
    line-height: 1;
    font-size: 0.65rem;
    padding: 1px 5px;
}

@media (max-width: 575.98px) {
    .chart-row {
        align-items: flex-start;
        flex-wrap: wrap;
        padding: 8px;
    }

    .chart-row > select {
        flex: 1 0 100%;
        width: 100% !important;
    }

    .reorder .btn {
        min-height: 30px;
    }
}
</style>
