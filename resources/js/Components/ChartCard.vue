<script setup>
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    title: { type: String, default: '' },
    type: { type: String, required: true },   // 'doughnut' | 'pie' | 'bar' | 'line'
    data: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
    height: { type: String, default: '260px' },
    // Tuỳ chọn hiển thị (điều khiển từ chart settings)
    showLegend: { type: Boolean, default: true },
    showGrid: { type: Boolean, default: true },
    showValues: { type: Boolean, default: false },
});

const canvas = ref(null);
let chart = null;

// Plugin vẽ nhãn số liệu trực tiếp (không cần thư viện ngoài)
const valuePlugin = {
    id: 'inlineValues',
    afterDatasetsDraw(c) {
        if (!props.showValues) return;
        const { ctx } = c;
        ctx.save();
        ctx.font = '600 11px sans-serif';
        ctx.fillStyle = '#172b4d';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        c.data.datasets.forEach((ds, di) => {
            const meta = c.getDatasetMeta(di);
            if (meta.hidden) return;
            meta.data.forEach((el, i) => {
                const v = ds.data[i];
                if (v === null || v === undefined || v === 0) return;
                const pos = typeof el.tooltipPosition === 'function' ? el.tooltipPosition() : { x: el.x, y: el.y };
                ctx.fillText(v, pos.x, pos.y);
            });
        });
        ctx.restore();
    },
};

const render = () => {
    if (chart) { chart.destroy(); chart = null; }
    if (!canvas.value) return;

    const base = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: props.showLegend, labels: { boxWidth: 12, font: { size: 11 } } },
        },
        scales: {
            x: { grid: { display: props.showGrid } },
            y: { grid: { display: props.showGrid } },
        },
    };
    // Doughnut/pie không có trục -> bỏ scales để tránh cảnh báo
    if (props.type === 'doughnut' || props.type === 'pie') {
        delete base.scales;
    }

    chart = new Chart(canvas.value.getContext('2d'), {
        type: props.type,
        data: props.data,
        options: deepMerge(base, props.options),
        plugins: [valuePlugin],
    });
};

// Gộp options: plugins một cấp, scales theo từng trục (giữ grid.display của base).
function deepMerge(a, b) {
    const out = { ...a, ...b };
    if (a.plugins || b.plugins) out.plugins = { ...(a.plugins || {}), ...(b.plugins || {}) };
    if (a.scales || b.scales) {
        const merged = { ...(a.scales || {}) };
        for (const axis of Object.keys(b.scales || {})) {
            const baseAxis = a.scales?.[axis] || {};
            const addAxis = b.scales[axis] || {};
            merged[axis] = {
                ...baseAxis,
                ...addAxis,
                grid: { ...(baseAxis.grid || {}), ...(addAxis.grid || {}) },
            };
        }
        out.scales = merged;
    }
    return out;
}

onMounted(render);
watch(
    () => [props.data, props.type, props.showLegend, props.showGrid, props.showValues, props.options],
    render,
    { deep: true }
);
onBeforeUnmount(() => { if (chart) chart.destroy(); });
</script>

<template>
    <div class="chart-card">
        <h6 v-if="title" class="chart-title">{{ title }}</h6>
        <div class="chart-canvas" :style="{ height }">
            <canvas ref="canvas"></canvas>
        </div>
    </div>
</template>

<style scoped>
.chart-card {
    background: var(--app-surface, #fff);
    border: 1px solid var(--app-border, #e4e6ea);
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 2px 8px rgba(9, 30, 66, 0.04);
}

.chart-title {
    font-weight: 700;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: var(--app-text, #44546f);
    margin-bottom: 12px;
}

.chart-canvas {
    position: relative;
    width: 100%;
}
</style>
