<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TaskModal from '@/Components/TaskModal.vue';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
});

const PRIORITY = {
    urgent: { label: 'Khẩn cấp', color: '#c9372c' },
    high: { label: 'Cao', color: '#d97008' },
    normal: { label: 'Bình thường', color: '#006adc' },
    low: { label: 'Thấp', color: '#18794e' },
};

const ALL_GROUPS = [
    { key: 'overdue', label: 'Quá hạn', icon: 'fa-triangle-exclamation', color: '#c9372c' },
    { key: 'today', label: 'Hôm nay', icon: 'fa-sun', color: '#976400' },
    { key: 'week', label: 'Trong 7 ngày tới', icon: 'fa-calendar-week', color: '#006adc' },
    { key: 'later', label: 'Sau này', icon: 'fa-calendar', color: '#44546f' },
    { key: 'none', label: 'Chưa có hạn', icon: 'fa-inbox', color: '#7a869a' },
];

const activeView = ref('today');
const search = ref('');
const priorityFilter = ref('');
const dueFilter = ref('');

const isDone = (task) => !!task.status?.is_completed;
const isPriorityTask = (task) => ['urgent', 'high'].includes(task.priority);
const pendingTasks = computed(() => props.tasks.filter((task) => !isDone(task)));
const overdueCount = computed(() => pendingTasks.value.filter((task) => task.due_group === 'overdue').length);
const todayCount = computed(() => pendingTasks.value.filter((task) => task.due_group === 'today').length);
const priorityCount = computed(() => pendingTasks.value.filter(isPriorityTask).length);
const todayAttentionCount = computed(() => pendingTasks.value.filter((task) =>
    ['overdue', 'today'].includes(task.due_group) || isPriorityTask(task)
).length);

const todayGroups = computed(() => {
    const groups = [
        { key: 'overdue', label: 'Cần xử lý ngay', subtitle: 'Những việc đã quá hạn', icon: 'fa-triangle-exclamation', color: '#c9372c', items: pendingTasks.value.filter((task) => task.due_group === 'overdue') },
        { key: 'today', label: 'Hôm nay', subtitle: 'Việc đến hạn trong ngày', icon: 'fa-sun', color: '#976400', items: pendingTasks.value.filter((task) => task.due_group === 'today') },
        { key: 'priority', label: 'Ưu tiên trọng tâm', subtitle: 'Việc khẩn cấp hoặc ưu tiên cao', icon: 'fa-bullseye', color: '#a85d00', items: pendingTasks.value.filter((task) => isPriorityTask(task) && !['overdue', 'today'].includes(task.due_group)) },
        { key: 'week', label: 'Sắp tới', subtitle: 'Đến hạn trong 7 ngày tới', icon: 'fa-calendar-week', color: '#006adc', items: pendingTasks.value.filter((task) => task.due_group === 'week' && !isPriorityTask(task)) },
    ];

    return groups.filter((group) => group.items.length);
});

const matchesFilters = (task) => {
    const query = search.value.trim().toLowerCase();
    if (query && !`${task.title} ${task.code} ${task.board_name} ${task.column_name}`.toLowerCase().includes(query)) return false;
    if (priorityFilter.value && task.priority !== priorityFilter.value) return false;
    if (dueFilter.value && task.due_group !== dueFilter.value) return false;
    return true;
};

const allGroups = computed(() =>
    ALL_GROUPS.map((group) => ({ ...group, items: props.tasks.filter((task) => matchesFilters(task) && task.due_group === group.key) }))
        .filter((group) => group.items.length)
);
const hasAllFilters = computed(() => !!(search.value || priorityFilter.value || dueFilter.value));
const clearFilters = () => { search.value = ''; priorityFilter.value = ''; dueFilter.value = ''; };

const modalTaskId = ref(null);
const modalBoardId = ref(null);
const modalCanEdit = ref(false);
const modalCanManage = ref(false);
const openTask = (task) => {
    modalTaskId.value = task.id;
    modalBoardId.value = task.board_id;
    modalCanEdit.value = !!task.can_edit;
    modalCanManage.value = !!task.can_manage;
};
const closeTask = () => { modalTaskId.value = null; };
</script>

<template>
    <Head title="Task của tôi" />
    <AuthenticatedLayout>
        <div class="mt-page">
            <header class="mt-hero">
                <div class="mt-hero__icon"><i class="fas fa-user-check"></i></div>
                <div class="mt-hero__text">
                    <span class="mt-eyebrow">Không gian làm việc cá nhân</span>
                    <h1 class="mt-hero__title">Task của tôi</h1>
                </div>
                <span class="mt-count"><i class="fas fa-list-check mr-1"></i>{{ pendingTasks.length }} đang mở</span>
            </header>

            <nav class="mt-tabs" aria-label="Chế độ xem công việc">
                <button type="button" class="mt-tab" :class="{ active: activeView === 'today' }" @click="activeView = 'today'">
                    <i class="fas fa-sun"></i> Hôm nay
                    <span>{{ todayAttentionCount }}</span>
                </button>
                <button type="button" class="mt-tab" :class="{ active: activeView === 'all' }" @click="activeView = 'all'">
                    <i class="fas fa-list"></i> Tất cả
                    <span>{{ tasks.length }}</span>
                </button>
            </nav>

            <template v-if="activeView === 'today'">
                <section class="mt-today-intro">
                    <div>
                        <span class="mt-eyebrow">Kế hoạch trong ngày</span>
                        <h2>{{ overdueCount ? 'Hãy xử lý các việc quá hạn trước.' : todayCount ? 'Đây là những việc cần hoàn thành hôm nay.' : 'Hôm nay khá nhẹ nhàng.' }}</h2>
                    </div>
                    <div class="mt-stat-row">
                        <span class="mt-stat mt-stat--danger"><strong>{{ overdueCount }}</strong> quá hạn</span>
                        <span class="mt-stat mt-stat--today"><strong>{{ todayCount }}</strong> đến hạn hôm nay</span>
                        <span class="mt-stat"><strong>{{ priorityCount }}</strong> ưu tiên cao</span>
                    </div>
                </section>

                <div v-if="!todayGroups.length" class="mt-empty">
                    <i class="fas fa-mug-hot"></i>
                    <h3>Mọi việc cấp bách đã được xử lý</h3>
                    <p class="mb-0">Bạn không có task quá hạn, đến hạn hôm nay hoặc ưu tiên cao.</p>
                </div>

                <section v-for="group in todayGroups" :key="group.key" class="mt-group" :style="{ '--mt-accent': group.color }">
                    <div class="mt-group__head">
                        <span class="mt-group__icon"><i class="fas" :class="group.icon"></i></span>
                        <div><h3>{{ group.label }}</h3><p>{{ group.subtitle }}</p></div>
                        <span class="mt-group__count">{{ group.items.length }}</span>
                    </div>
                    <div class="mt-task-list">
                        <article v-for="task in group.items" :key="task.id" class="mt-task" @click="openTask(task)">
                            <div class="mt-task-main">
                                <div class="mt-tags"><span class="task-code">{{ task.code }}</span><span v-for="label in task.labels" :key="label.id" class="label-chip" :style="{ backgroundColor: label.color }">{{ label.name }}</span></div>
                                <h4 class="mt-title">{{ task.title }}</h4>
                                <div class="mt-sub"><i class="fas fa-columns"></i><span class="mt-board" :title="task.board_name">{{ task.board_name }}</span><span class="mt-col"> · {{ task.column_name }}</span></div>
                            </div>
                            <div class="mt-task-meta"><span v-if="task.status" class="status-badge" :style="{ color: task.status.color, borderColor: task.status.color }">{{ task.status.name }}</span><span v-if="PRIORITY[task.priority]" class="priority-badge" :style="{ '--priority': PRIORITY[task.priority].color }">{{ PRIORITY[task.priority].label }}</span><span v-if="task.formatted_due_date" class="due" :class="`due-${task.due_group}`"><i class="far fa-clock"></i> {{ task.formatted_due_date }}</span><span v-if="task.checklist_total" class="checklist"><i class="far fa-check-square"></i> {{ task.checklist_done }}/{{ task.checklist_total }}</span></div>
                        </article>
                    </div>
                </section>
            </template>

            <template v-else>
                <section class="mt-all-toolbar">
                    <div><span class="mt-eyebrow">Toàn bộ công việc</span><h2>Theo dõi và tìm lại task của bạn</h2></div>
                    <div class="mt-filters">
                        <label class="sr-only" for="task-search">Tìm kiếm task</label>
                        <div class="mt-search"><i class="fas fa-search"></i><input id="task-search" v-model="search" type="search" placeholder="Tìm theo task, mã, bảng..." /></div>
                        <select v-model="priorityFilter" aria-label="Lọc theo ưu tiên"><option value="">Mọi ưu tiên</option><option v-for="(item, key) in PRIORITY" :key="key" :value="key">{{ item.label }}</option></select>
                        <select v-model="dueFilter" aria-label="Lọc theo hạn"><option value="">Mọi thời hạn</option><option v-for="group in ALL_GROUPS" :key="group.key" :value="group.key">{{ group.label }}</option></select>
                        <button v-if="hasAllFilters" type="button" class="mt-reset" @click="clearFilters">Xóa lọc</button>
                    </div>
                </section>
                <div v-if="!allGroups.length" class="mt-empty"><i class="fas fa-search"></i><h3>Không tìm thấy công việc phù hợp</h3><p class="mb-0">Hãy thử thay đổi từ khóa hoặc bộ lọc.</p></div>
                <section v-for="group in allGroups" :key="group.key" class="mt-group" :style="{ '--mt-accent': group.color }">
                    <div class="mt-group__head"><span class="mt-group__icon"><i class="fas" :class="group.icon"></i></span><div><h3>{{ group.label }}</h3></div><span class="mt-group__count">{{ group.items.length }}</span></div>
                    <div class="mt-task-list"><article v-for="task in group.items" :key="task.id" class="mt-task" @click="openTask(task)"><div class="mt-task-main"><div class="mt-tags"><span class="task-code">{{ task.code }}</span><span v-for="label in task.labels" :key="label.id" class="label-chip" :style="{ backgroundColor: label.color }">{{ label.name }}</span></div><h4 class="mt-title" :class="{ done: isDone(task) }">{{ task.title }}</h4><div class="mt-sub"><i class="fas fa-columns"></i><span class="mt-board" :title="task.board_name">{{ task.board_name }}</span><span class="mt-col"> · {{ task.column_name }}</span></div></div><div class="mt-task-meta"><span v-if="task.status" class="status-badge" :style="{ color: task.status.color, borderColor: task.status.color }">{{ task.status.name }}</span><span v-if="PRIORITY[task.priority]" class="priority-badge" :style="{ '--priority': PRIORITY[task.priority].color }">{{ PRIORITY[task.priority].label }}</span><span v-if="task.formatted_due_date" class="due" :class="`due-${task.due_group}`"><i class="far fa-clock"></i> {{ task.formatted_due_date }}</span></div></article></div>
                </section>
            </template>
        </div>
        <TaskModal v-if="modalTaskId" :task-id="modalTaskId" :board-id="modalBoardId" :can-edit="modalCanEdit" :can-manage="modalCanManage" :edit-query="{ return: 'my-tasks' }" @close="closeTask" />
    </AuthenticatedLayout>
</template>

<style scoped>
.mt-page { max-width: 1120px; margin: 0 auto; padding: 20px 16px 40px; }
.mt-hero { position: relative; display: flex; align-items: center; gap: 16px; padding: 20px 22px; margin-bottom: 18px; color: #fff; background: linear-gradient(125deg, var(--app-accent-dark), var(--app-accent), var(--app-accent-2)); border-radius: 18px; box-shadow: 0 10px 24px rgba(102, 51, 0, .2); overflow: hidden; }
.mt-hero::after { content: ''; position: absolute; width: 210px; height: 210px; right: -66px; top: -100px; border: 36px solid rgba(255,255,255,.1); border-radius: 50%; }
.mt-hero__icon { z-index: 1; display: grid; flex: 0 0 48px; width: 48px; height: 48px; place-items: center; font-size: 1.2rem; background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.24); border-radius: 14px; }
.mt-hero__text, .mt-count { z-index: 1; }.mt-hero__text { flex: 1; }.mt-eyebrow { display: block; font-size: .71rem; font-weight: 800; letter-spacing: .7px; text-transform: uppercase; color: var(--app-accent); }.mt-hero .mt-eyebrow { color: rgba(255,255,255,.78); }.mt-hero__title { margin: 2px 0; font-size: 1.55rem; font-weight: 800; }.mt-count { padding: 7px 12px; font-size: .78rem; font-weight: 700; background: rgba(50,25,0,.3); border: 1px solid rgba(255,255,255,.18); border-radius: 20px; white-space: nowrap; }
.mt-tabs { display: inline-flex; gap: 4px; padding: 4px; margin-bottom: 24px; background: var(--app-bg); border: 1px solid var(--app-border); border-radius: 12px; }.mt-tab { border: 0; border-radius: 8px; padding: 8px 13px; font-size: .83rem; font-weight: 700; color: var(--app-text-muted); background: transparent; cursor: pointer; }.mt-tab span { display: inline-block; min-width: 19px; margin-left: 4px; padding: 0 5px; font-size: .68rem; border-radius: 10px; background: rgba(127,127,127,.12); }.mt-tab.active { color: #fff; background: var(--app-accent); box-shadow: 0 2px 6px rgba(102,51,0,.22); }.mt-tab.active span { background: rgba(255,255,255,.22); }
.mt-today-intro, .mt-all-toolbar { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; padding: 0 4px; margin-bottom: 22px; }.mt-today-intro h2, .mt-all-toolbar h2 { margin: 4px 0 0; font-size: 1.15rem; font-weight: 750; color: var(--app-text); }.mt-stat-row { display: flex; flex-wrap: wrap; gap: 7px; justify-content: flex-end; }.mt-stat { padding: 5px 9px; font-size: .72rem; font-weight: 600; color: var(--app-text-muted); background: var(--app-surface); border: 1px solid var(--app-border); border-radius: 16px; }.mt-stat strong { color: var(--app-text); }.mt-stat--danger { color: #c9372c; border-color: rgba(201,55,44,.3); }.mt-stat--danger strong { color: #c9372c; }.mt-stat--today { color: #976400; border-color: rgba(151,100,0,.28); }.mt-stat--today strong { color: #976400; }
.mt-group { margin-bottom: 26px; }.mt-group__head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }.mt-group__icon { display: grid; width: 30px; height: 30px; place-items: center; color: var(--mt-accent); background: color-mix(in srgb, var(--mt-accent) 14%, transparent); border-radius: 9px; font-size: .8rem; }.mt-group__head h3 { margin: 0; font-size: .98rem; font-weight: 750; color: var(--app-text); }.mt-group__head p { margin: 1px 0 0; font-size: .73rem; color: var(--app-text-muted); }.mt-group__count { min-width: 22px; margin-left: auto; padding: 2px 8px; text-align: center; font-size: .7rem; font-weight: 800; color: var(--mt-accent); background: color-mix(in srgb, var(--mt-accent) 13%, transparent); border-radius: 14px; }
.mt-task-list { display: flex; flex-direction: column; gap: 9px; }.mt-task { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 13px 15px; background: var(--app-surface); border: 1px solid var(--app-border); border-left: 3px solid var(--mt-accent); border-radius: 12px; cursor: pointer; transition: transform .18s ease, box-shadow .18s ease; }.mt-task:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102,51,0,.14); }.mt-task-main { min-width: 0; }.mt-tags, .mt-task-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }.mt-tags { margin-bottom: 3px; }.task-code { padding: 1px 6px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .66rem; font-weight: 700; color: var(--app-text-muted); background: rgba(127,127,127,.1); border-radius: 5px; }.label-chip { padding: 2px 7px; font-size: .65rem; font-weight: 700; line-height: 1.1; color: #fff; border-radius: 5px; }.mt-title { margin: 0; font-size: .98rem; font-weight: 650; line-height: 1.35; color: var(--app-text); overflow-wrap: anywhere; }.mt-title.done { color: var(--app-text-muted); text-decoration: line-through; }.mt-sub { display: flex; align-items: center; min-width: 0; margin-top: 4px; font-size: .76rem; color: var(--app-text-muted); }.mt-sub i { margin-right: 5px; color: var(--app-accent-2); }.mt-board { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.mt-col { flex-shrink: 0; white-space: nowrap; }.mt-task-meta { justify-content: flex-end; flex-shrink: 0; }.status-badge, .priority-badge, .due, .checklist { padding: 2px 7px; font-size: .7rem; font-weight: 650; white-space: nowrap; }.status-badge { border: 1px solid currentColor; border-radius: 12px; }.priority-badge { color: var(--priority); background: color-mix(in srgb, var(--priority) 12%, transparent); border-radius: 12px; }.due-overdue { color: #c9372c; }.due-today { color: #976400; }.checklist { color: var(--app-text-muted); }
.mt-all-toolbar { align-items: flex-end; }.mt-filters { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 7px; }.mt-search { display: flex; align-items: center; width: 215px; padding: 0 9px; background: var(--app-surface); border: 1px solid var(--app-border); border-radius: 8px; }.mt-search i { color: var(--app-text-muted); font-size: .75rem; }.mt-search input, .mt-filters select { height: 33px; border: 0; outline: 0; color: var(--app-text); background: transparent; font-size: .76rem; }.mt-search input { min-width: 0; width: 100%; padding-left: 7px; }.mt-filters select { padding: 0 7px; background: var(--app-surface); border: 1px solid var(--app-border); border-radius: 8px; }.mt-reset { border: 0; padding: 0 7px; color: var(--app-accent); background: transparent; font-size: .75rem; font-weight: 700; cursor: pointer; }
.mt-empty { padding: 48px 20px; text-align: center; color: var(--app-text-muted); background: var(--app-surface); border: 1px dashed var(--app-border); border-radius: 16px; }.mt-empty i { display: block; margin-bottom: 10px; font-size: 2rem; color: var(--app-accent-2); }.mt-empty h3 { margin: 0 0 5px; color: var(--app-text); font-size: 1rem; }
@media (max-width: 767.98px) { .mt-page { padding: 6px 0 26px; }.mt-hero { align-items: flex-start; flex-wrap: wrap; padding: 16px; }.mt-count { margin-left: 64px; }.mt-today-intro, .mt-all-toolbar { align-items: flex-start; flex-direction: column; gap: 12px; }.mt-stat-row, .mt-filters { justify-content: flex-start; }.mt-search { width: 100%; }.mt-task { align-items: flex-start; flex-direction: column; gap: 10px; }.mt-task-meta { justify-content: flex-start; }.mt-col { max-width: 40vw; overflow: hidden; text-overflow: ellipsis; }.mt-tabs { margin-bottom: 20px; } }
</style>
