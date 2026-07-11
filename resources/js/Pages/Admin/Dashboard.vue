<script setup>
import { ref, computed, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ChartCard from '@/Components/ChartCard.vue';
import ChartSettingsPanel from '@/Components/ChartSettingsPanel.vue';
import { useChartSettings } from '@/composables/useChartSettings';

const props = defineProps({
    totals: { type: Object, default: () => ({}) },
    statusDistribution: { type: Array, default: () => [] },
    topBoards: { type: Array, default: () => [] },
});

const cssVar = (name, fb) => getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fb;
const ACCENT = cssVar('--app-accent', '#663300');
const ACCENT2 = cssVar('--app-accent-2', '#a5763f');

const DEFAULTS = {
    range: 30, legend: true, grid: true, values: false,
    charts: {
        growth: { visible: true, type: 'line', order: 1 },
        status: { visible: true, type: 'doughnut', order: 2 },
        topBoards: { visible: true, type: 'bar', order: 3 },
    },
};
const CHART_META = [
    { key: 'growth', label: 'Tăng trưởng theo thời gian', types: [{ value: 'line', label: 'Đường' }, { value: 'bar', label: 'Cột' }] },
    { key: 'status', label: 'Phân bổ trạng thái', types: [{ value: 'doughnut', label: 'Tròn' }, { value: 'pie', label: 'Quạt' }, { value: 'bar', label: 'Cột' }] },
    { key: 'topBoards', label: 'Top bảng hoạt động', types: [{ value: 'bar', label: 'Cột' }] },
];

const { settings, loaded } = useChartSettings('admin', DEFAULTS);

const growth = ref({ labels: [], users: [], boards: [], tasks: [] });
const fmt = (d) => d.toISOString().slice(0, 10);
const fetchGrowth = async () => {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - (settings.value.range - 1));
    const { data } = await axios.get(route('admin.dashboard.growth'), {
        params: { date_range: `${fmt(from)} to ${fmt(to)}` },
    });
    growth.value = data;
};
watch(loaded, (v) => { if (v) fetchGrowth(); }, { immediate: true });
watch(() => settings.value.range, () => { if (loaded.value) fetchGrowth(); });

const kpis = computed(() => [
    { label: 'Người dùng', value: props.totals.users ?? 0, icon: 'fa-users', color: '#006adc' },
    { label: 'Bảng', value: props.totals.boards ?? 0, icon: 'fa-clipboard', color: ACCENT },
    { label: 'Công việc', value: props.totals.tasks ?? 0, icon: 'fa-list-check', color: '#18794e' },
]);

const defs = computed(() => ({
    growth: {
        title: `Tăng trưởng (${settings.value.range} ngày)`,
        data: {
            labels: growth.value.labels,
            datasets: [
                { label: 'Người dùng', data: growth.value.users, borderColor: '#006adc', backgroundColor: '#006adc', tension: 0.3 },
                { label: 'Bảng', data: growth.value.boards, borderColor: ACCENT, backgroundColor: ACCENT, tension: 0.3 },
                { label: 'Công việc', data: growth.value.tasks, borderColor: ACCENT2, backgroundColor: ACCENT2, tension: 0.3 },
            ],
        },
        options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        hasData: true,
    },
    status: {
        title: 'Phân bổ trạng thái toàn hệ thống',
        data: {
            labels: props.statusDistribution.map((s) => s.name),
            datasets: [{ data: props.statusDistribution.map((s) => s.count), backgroundColor: props.statusDistribution.map((s) => s.color), borderWidth: 0 }],
        },
        options: {},
        hasData: props.statusDistribution.length > 0,
    },
    topBoards: {
        title: 'Top bảng hoạt động nhiều nhất',
        data: {
            labels: props.topBoards.map((b) => b.name),
            datasets: [{ label: 'Số hoạt động', data: props.topBoards.map((b) => b.activity), backgroundColor: ACCENT, borderRadius: 6 }],
        },
        options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } },
        hasData: props.topBoards.length > 0,
    },
}));

const visibleCharts = computed(() =>
    [...CHART_META]
        .sort((a, b) => (settings.value.charts[a.key]?.order ?? 0) - (settings.value.charts[b.key]?.order ?? 0))
        .filter((c) => settings.value.charts[c.key]?.visible && defs.value[c.key]?.hasData)
);
</script>

<template>
    <Head title="Thống kê hệ thống" />
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Thống kê hệ thống</h3>
            <ChartSettingsPanel :settings="settings" :charts="CHART_META" />
        </div>

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
                :class="{ 'chart-wide': c.key === 'growth' }"
                :title="defs[c.key].title"
                :type="settings.charts[c.key].type"
                :data="defs[c.key].data"
                :options="defs[c.key].options"
                :height="c.key === 'growth' ? '340px' : '300px'"
                :show-legend="settings.legend"
                :show-grid="settings.grid"
                :show-values="settings.values" />
        </div>
    </AdminLayout>
</template>

<style scoped>
.kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.kpi-card { display: flex; align-items: center; gap: 12px; background: var(--app-surface, #fff); border: 1px solid var(--app-border, #e4e6ea); border-radius: 12px; padding: 14px 16px; }
.kpi-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex: 0 0 auto; }
.kpi-value { font-size: 1.5rem; font-weight: 800; line-height: 1; color: var(--app-text, #172b4d); }
.kpi-label { font-size: 0.78rem; color: var(--app-text-muted); }
.chart-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.chart-wide { grid-column: 1 / -1; }

@media (max-width: 767.98px) {
    .kpi-grid { grid-template-columns: 1fr; }
    .chart-grid { grid-template-columns: 1fr; }
}
</style>
