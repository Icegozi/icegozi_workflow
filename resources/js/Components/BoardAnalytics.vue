<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import ChartCard from '@/Components/ChartCard.vue';
import ChartSettingsPanel from '@/Components/ChartSettingsPanel.vue';
import { useChartSettings } from '@/composables/useChartSettings';

const props = defineProps({
    boardId: { type: Number, required: true },
});

const cssVar = (name, fb) => getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fb;
const ACCENT = cssVar('--app-accent', '#663300');
const ACCENT2 = cssVar('--app-accent-2', '#a5763f');

// ---- Thiết lập biểu đồ (lưu DB theo user) ----
const DEFAULTS = {
    range: 14, legend: true, grid: true, values: false,
    charts: {
        status: { visible: true, type: 'doughnut', order: 1 },
        priority: { visible: true, type: 'bar', order: 2 },
        workload: { visible: true, type: 'bar', order: 3 },
        overdue: { visible: true, type: 'bar', order: 4 },
        timeline: { visible: true, type: 'line', order: 5 },
    },
};
const CHART_META = [
    { key: 'status', label: 'Theo trạng thái', types: [{ value: 'doughnut', label: 'Tròn' }, { value: 'pie', label: 'Quạt' }, { value: 'bar', label: 'Cột' }] },
    { key: 'priority', label: 'Theo độ ưu tiên', types: [{ value: 'bar', label: 'Cột' }, { value: 'doughnut', label: 'Tròn' }, { value: 'pie', label: 'Quạt' }] },
    { key: 'workload', label: 'Khối lượng theo người', types: [{ value: 'bar', label: 'Cột' }] },
    { key: 'overdue', label: 'Quá hạn theo người', types: [{ value: 'bar', label: 'Cột' }] },
    { key: 'timeline', label: 'Tạo mới & hoàn thành', types: [{ value: 'line', label: 'Đường' }, { value: 'bar', label: 'Cột' }] },
];

const { settings, loaded } = useChartSettings('board', DEFAULTS);

// ---- Dữ liệu ----
const data = ref(null);
const loadingData = ref(true);
const fetchData = async () => {
    loadingData.value = true;
    try {
        const res = await axios.get(route('boards.analytics', props.boardId), { params: { days: settings.value.range } });
        data.value = res.data;
    } catch (e) {
        data.value = null;
    } finally {
        loadingData.value = false;
    }
};
watch(loaded, (v) => { if (v) fetchData(); }, { immediate: true });
watch(() => settings.value.range, () => { if (loaded.value) fetchData(); });

const kpis = computed(() => {
    const t = data.value?.totals || {};
    return [
        { label: 'Tổng công việc', value: t.tasks ?? 0, icon: 'fa-list-check', color: ACCENT },
        { label: 'Đã hoàn thành', value: t.done ?? 0, icon: 'fa-circle-check', color: '#18794e' },
        { label: 'Quá hạn', value: t.overdue ?? 0, icon: 'fa-triangle-exclamation', color: '#c9372c' },
        { label: 'Thành viên', value: t.members ?? 0, icon: 'fa-users', color: '#006adc' },
    ];
});

// ---- Dữ liệu từng biểu đồ ----
const defs = computed(() => ({
    status: {
        title: 'Công việc theo trạng thái',
        data: {
            labels: (data.value?.byStatus || []).map((s) => s.name),
            datasets: [{ data: (data.value?.byStatus || []).map((s) => s.count), backgroundColor: (data.value?.byStatus || []).map((s) => s.color), borderWidth: 0 }],
        },
        options: { plugins: { legend: { display: settings.value.legend } } },
        hasData: (data.value?.byStatus || []).length > 0,
    },
    priority: {
        title: 'Công việc theo độ ưu tiên',
        data: {
            labels: (data.value?.byPriority || []).map((p) => p.label),
            datasets: [{ label: 'Số công việc', data: (data.value?.byPriority || []).map((p) => p.count), backgroundColor: (data.value?.byPriority || []).map((p) => p.color), borderRadius: 6 }],
        },
        options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        hasData: true,
    },
    workload: {
        title: 'Khối lượng theo người',
        data: {
            labels: (data.value?.workload || []).map((w) => w.name),
            datasets: [
                { label: 'Hoàn thành', data: (data.value?.workload || []).map((w) => w.done), backgroundColor: ACCENT, borderRadius: 4 },
                { label: 'Chưa xong', data: (data.value?.workload || []).map((w) => w.pending), backgroundColor: ACCENT2, borderRadius: 4 },
            ],
        },
        options: { scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } },
        hasData: (data.value?.workload || []).length > 0,
    },
    overdue: {
        title: 'Quá hạn theo người',
        data: {
            labels: (data.value?.overdueByAssignee || []).map((o) => o.name),
            datasets: [{ label: 'Quá hạn', data: (data.value?.overdueByAssignee || []).map((o) => o.count), backgroundColor: '#c9372c', borderRadius: 6 }],
        },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        hasData: (data.value?.overdueByAssignee || []).length > 0,
    },
    timeline: {
        title: `Tạo mới & hoàn thành (${settings.value.range} ngày)`,
        data: {
            labels: data.value?.timeline?.labels || [],
            datasets: [
                { label: 'Tạo mới', data: data.value?.timeline?.created || [], borderColor: ACCENT2, backgroundColor: ACCENT2, tension: 0.3 },
                { label: 'Hoàn thành', data: data.value?.timeline?.completed || [], borderColor: ACCENT, backgroundColor: ACCENT, tension: 0.3 },
            ],
        },
        options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        hasData: true,
    },
}));

// Danh sách chart cần render: theo order, chỉ visible + có dữ liệu
const visibleCharts = computed(() =>
    [...CHART_META]
        .sort((a, b) => (settings.value.charts[a.key]?.order ?? 0) - (settings.value.charts[b.key]?.order ?? 0))
        .filter((c) => settings.value.charts[c.key]?.visible && defs.value[c.key]?.hasData)
);
</script>

<template>
    <div class="board-analytics px-2">
        <div class="d-flex justify-content-end mb-2">
            <ChartSettingsPanel :settings="settings" :charts="CHART_META" />
        </div>

        <div v-if="loadingData && !data" class="text-center p-5 text-muted">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
        </div>

        <template v-else-if="data">
            <div class="kpi-grid mb-3">
                <div v-for="k in kpis" :key="k.label" class="kpi-card">
                    <div class="kpi-icon" :style="{ color: k.color, background: k.color + '1a' }">
                        <i class="fas" :class="k.icon"></i>
                    </div>
                    <div>
                        <div class="kpi-value">{{ k.value }}</div>
                        <div class="kpi-label">{{ k.label }}</div>
                    </div>
                </div>
            </div>

            <div class="chart-grid">
                <ChartCard v-for="c in visibleCharts" :key="c.key"
                    :class="{ 'chart-wide': c.key === 'timeline' }"
                    :title="defs[c.key].title"
                    :type="settings.charts[c.key].type"
                    :data="defs[c.key].data"
                    :options="defs[c.key].options"
                    :show-legend="settings.legend"
                    :show-grid="settings.grid"
                    :show-values="settings.values" />
            </div>
        </template>

        <div v-else class="text-center text-muted p-5">Không tải được số liệu.</div>
    </div>
</template>

<style scoped>
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
.kpi-card { display: flex; align-items: center; gap: 12px; background: var(--app-surface, #fff); border: 1px solid var(--app-border, #e4e6ea); border-radius: 12px; padding: 14px 16px; }
.kpi-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex: 0 0 auto; }
.kpi-value { font-size: 1.5rem; font-weight: 800; line-height: 1; color: var(--app-text, #172b4d); }
.kpi-label { font-size: 0.78rem; color: var(--app-text-muted); }
.chart-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.chart-wide { grid-column: 1 / -1; }

@media (max-width: 767.98px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .chart-grid { grid-template-columns: 1fr; }
}

@media (max-width: 399.98px) {
    .kpi-grid { grid-template-columns: 1fr; }
    .kpi-card { padding: 12px; }
}
</style>
