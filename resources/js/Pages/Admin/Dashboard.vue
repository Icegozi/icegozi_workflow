<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import Chart from 'chart.js/auto';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const canvas = ref(null);
let chart = null;

const today = new Date();
const thirtyAgo = new Date(new Date().setDate(today.getDate() - 30));
const fmt = (d) => d.toISOString().slice(0, 10);

const from = ref(fmt(thirtyAgo));
const to = ref(fmt(today));

const fetchData = async () => {
    const range = `${from.value} to ${to.value}`;
    const { data } = await axios.get(route('admin.dashboard.user-registrations'), {
        params: { date_range: range },
    });
    await nextTick();
    if (chart) chart.destroy();
    if (!data.labels || !data.labels.length) return;
    chart = new Chart(canvas.value.getContext('2d'), {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: data.datasets.map((ds) => ({
                label: ds.label || 'Đăng ký',
                data: ds.data,
                borderColor: ds.borderColor || 'rgb(75, 192, 192)',
                tension: 0.1,
                fill: false,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Số lượng đăng ký' } } },
            plugins: { legend: { position: 'top' } },
        },
    });
};

onMounted(fetchData);
watch([from, to], fetchData);
</script>

<template>
    <Head title="Thống kê người dùng" />
    <AdminLayout>
        <h3>Thống kê người dùng đã đăng ký</h3>

        <div class="form-row mb-3" style="max-width: 480px;">
            <div class="col">
                <label class="small font-weight-bold">Từ ngày</label>
                <input type="date" class="form-control" v-model="from" :max="to">
            </div>
            <div class="col">
                <label class="small font-weight-bold">Đến ngày</label>
                <input type="date" class="form-control" v-model="to" :min="from">
            </div>
        </div>

        <div style="position: relative; height: 60vh; width: 100%;">
            <canvas ref="canvas"></canvas>
        </div>
    </AdminLayout>
</template>
