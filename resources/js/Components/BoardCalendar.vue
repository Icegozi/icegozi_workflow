<script setup>
import { ref, computed, watch } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps({
    tasks: { type: Array, default: () => [] },   // danh sách task phẳng (tham chiếu chung với board)
    canEdit: { type: Boolean, default: false },
});
const emit = defineEmits(['open', 'reschedule']);

const WEEKDAYS = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
const PRIORITY_COLOR = { urgent: '#e5484d', high: '#f76808', normal: '#006adc', low: '#18794e' };

// Ngày đầu tháng đang xem
const viewDate = ref(startOfMonth(new Date()));

function startOfMonth(d) { return new Date(d.getFullYear(), d.getMonth(), 1); }
function pad(n) { return String(n).padStart(2, '0'); }
function keyOf(d) { return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; }

const monthLabel = computed(() =>
    `Tháng ${viewDate.value.getMonth() + 1} / ${viewDate.value.getFullYear()}`
);

const todayKey = keyOf(new Date());

// 42 ô (6 tuần), bắt đầu từ Thứ 2 của tuần chứa ngày 1
const cells = computed(() => {
    const first = viewDate.value;
    const offset = (first.getDay() + 6) % 7;   // 0=CN -> 6; T2 -> 0
    const start = new Date(first.getFullYear(), first.getMonth(), 1 - offset);
    const month = first.getMonth();
    return Array.from({ length: 42 }, (_, i) => {
        const d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
        const key = keyOf(d);
        return { key, day: d.getDate(), inMonth: d.getMonth() === month, isToday: key === todayKey };
    });
});

// Nhóm task theo ngày (+ 'none' cho chưa có hạn). Chỉ giữ task rơi trong lưới đang xem.
const buckets = ref({});
const rebuild = () => {
    const b = { none: [] };
    for (const c of cells.value) b[c.key] = [];
    for (const t of props.tasks) {
        if (!t.due_date) { b.none.push(t); continue; }
        if (b[t.due_date]) b[t.due_date].push(t);
    }
    buckets.value = b;
};
watch([viewDate, () => props.tasks.map((t) => `${t.id}:${t.due_date}`).join('|')], rebuild, { immediate: true });

const prevMonth = () => { viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() - 1, 1); };
const nextMonth = () => { viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + 1, 1); };
const goToday = () => { viewDate.value = startOfMonth(new Date()); };

// Khi task được thả vào một ô -> đổi hạn (key = 'YYYY-MM-DD' hoặc 'none' để xoá hạn)
const onDrop = (key, evt) => {
    const moved = evt.added;
    if (!moved) return;
    const task = moved.element;
    const dueDate = key === 'none' ? null : key;
    if (task.due_date === dueDate) return;
    emit('reschedule', { task, dueDate });
};
</script>

<template>
    <div class="board-calendar">
        <div class="cal-toolbar">
            <div class="cal-nav">
                <button class="btn btn-sm btn-light" @click="prevMonth"><i class="fas fa-chevron-left"></i></button>
                <strong class="cal-month">{{ monthLabel }}</strong>
                <button class="btn btn-sm btn-light" @click="nextMonth"><i class="fas fa-chevron-right"></i></button>
                <button class="btn btn-sm btn-outline-secondary ml-2" @click="goToday">Hôm nay</button>
            </div>
            <span class="text-muted small">Kéo thẻ sang ngày khác để đổi hạn</span>
        </div>

        <div class="cal-grid cal-head">
            <div v-for="w in WEEKDAYS" :key="w" class="cal-weekday">{{ w }}</div>
        </div>

        <div class="cal-grid">
            <div v-for="c in cells" :key="c.key" class="cal-cell" :class="{ 'out-month': !c.inMonth, 'is-today': c.isToday }">
                <div class="cal-daynum">{{ c.day }}</div>
                <draggable :list="buckets[c.key]" :group="'cal-tasks'" item-key="id" :disabled="!canEdit"
                    class="cal-drop" @change="(e) => onDrop(c.key, e)">
                    <template #item="{ element: t }">
                        <div class="cal-task" :style="{ borderLeftColor: PRIORITY_COLOR[t.priority] || '#c1c7d0' }"
                            :class="{ done: t.status?.is_completed }" @click="emit('open', t)">
                            <span class="cal-task-code">{{ t.code }}</span>
                            <span class="cal-task-title">{{ t.title }}</span>
                        </div>
                    </template>
                </draggable>
            </div>
        </div>

        <!-- Task chưa có hạn: kéo vào lịch để đặt hạn -->
        <div class="cal-unscheduled">
            <div class="small font-weight-bold text-muted mb-1">
                <i class="fas fa-inbox mr-1"></i>Chưa có hạn ({{ (buckets.none || []).length }})
            </div>
            <draggable :list="buckets.none" :group="'cal-tasks'" item-key="id" :disabled="!canEdit"
                class="cal-unscheduled-list" @change="(e) => onDrop('none', e)">
                <template #item="{ element: t }">
                    <div class="cal-task" :style="{ borderLeftColor: PRIORITY_COLOR[t.priority] || '#c1c7d0' }"
                        :class="{ done: t.status?.is_completed }" @click="emit('open', t)">
                        <span class="cal-task-code">{{ t.code }}</span>
                        <span class="cal-task-title">{{ t.title }}</span>
                    </div>
                </template>
            </draggable>
            <span v-if="!(buckets.none || []).length" class="text-muted small">Không có.</span>
        </div>
    </div>
</template>

<style scoped>
.board-calendar {
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 12px;
    padding: 12px;
}

.cal-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
}

.cal-nav {
    display: flex;
    align-items: center;
    gap: 8px;
}

.cal-month {
    min-width: 150px;
    text-align: center;
}

.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
}

.cal-head {
    margin-bottom: 6px;
}

.cal-weekday {
    text-align: center;
    font-weight: 700;
    font-size: 0.78rem;
    color: var(--app-text-muted);
    padding: 4px 0;
}

.cal-cell {
    min-height: 110px;
    background: var(--app-bg);
    border: 1px solid var(--app-border);
    border-radius: 8px;
    padding: 4px;
    display: flex;
    flex-direction: column;
}

.cal-cell.out-month {
    background: rgba(127, 127, 127, 0.08);
    opacity: 0.55;
}

.cal-cell.is-today {
    border-color: var(--app-accent, #663300);
    box-shadow: inset 0 0 0 1px var(--app-accent, #663300);
}

.cal-daynum {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--app-text);
    text-align: right;
    padding: 0 2px;
}

.cal-drop {
    flex: 1;
    min-height: 20px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 2px;
}

.cal-task {
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-left: 3px solid #c1c7d0;
    border-radius: 6px;
    padding: 3px 6px;
    cursor: pointer;
    font-size: 0.72rem;
    line-height: 1.2;
    box-shadow: 0 1px 2px rgba(9, 30, 66, 0.06);
    transition: transform 0.15s ease;
}

.cal-task:hover {
    transform: translateY(-1px);
}

.cal-task.done {
    opacity: 0.6;
}

.cal-task.done .cal-task-title {
    text-decoration: line-through;
}

.cal-task-code {
    display: block;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.6rem;
    font-weight: 700;
    color: var(--app-text-muted);
}

.cal-task-title {
    display: block;
    color: var(--app-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.cal-unscheduled {
    margin-top: 12px;
    border-top: 1px dashed var(--app-border);
    padding-top: 10px;
}

.cal-unscheduled-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    min-height: 24px;
}

.cal-unscheduled-list .cal-task {
    width: 160px;
}

@media (max-width: 575.98px) {
    .cal-cell { min-height: 78px; }
    .cal-task-code { display: none; }
}
</style>
