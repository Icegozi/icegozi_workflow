<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Phân bổ trạng thái toàn hệ thống (bỏ task đã xoá mềm — DB::table không áp global scope)
        $statusDistribution = DB::table('tasks')
            ->whereNull('tasks.deleted_at')
            ->leftJoin('statuses', 'tasks.status_id', '=', 'statuses.id')
            ->select('statuses.name', 'statuses.color', DB::raw('COUNT(*) as count'))
            ->groupBy('statuses.id', 'statuses.name', 'statuses.color')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name ?? 'Chưa đặt',
                'color' => $r->color ?? '#c1c7d0',
                'count' => (int) $r->count,
            ]);

        // Top board hoạt động nhiều nhất (theo số bản ghi lịch sử)
        $topBoards = DB::table('task_histories')
            ->join('tasks', 'task_histories.task_id', '=', 'tasks.id')
            ->join('columns', 'tasks.column_id', '=', 'columns.id')
            ->join('boards', 'columns.board_id', '=', 'boards.id')
            ->whereNull('task_histories.deleted_at')
            ->whereNull('tasks.deleted_at')
            ->whereNull('boards.deleted_at')
            ->select('boards.name', DB::raw('COUNT(*) as activity'))
            ->groupBy('boards.id', 'boards.name')
            ->orderByDesc('activity')
            ->limit(10)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'totals' => [
                'users' => DB::table('users')->count(),
                'boards' => DB::table('boards')->whereNull('deleted_at')->count(),
                'tasks' => DB::table('tasks')->whereNull('deleted_at')->count(),
            ],
            'statusDistribution' => $statusDistribution,
            'topBoards' => $topBoards,
        ]);
    }

    /** Tăng trưởng theo ngày: board / task / user tạo mới trong khoảng thời gian. */
    public function getGrowth(Request $request)
    {
        [$start, $end] = $this->parseRange($request->query('date_range'));

        $labels = [];
        $cur = $start->copy();
        while ($cur->lte($end)) {
            $labels[] = $cur->toDateString();
            $cur->addDay();
        }

        $series = fn (array $counts) => array_map(fn ($d) => $counts[$d] ?? 0, $labels);

        return response()->json([
            'labels' => $labels,
            'users' => $series($this->dailyCounts('users', $start, $end)),
            'boards' => $series($this->dailyCounts('boards', $start, $end)),
            'tasks' => $series($this->dailyCounts('tasks', $start, $end)),
        ]);
    }

    private function dailyCounts(string $table, Carbon $start, Carbon $end): array
    {
        $query = DB::table($table)
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as c'))
            ->whereBetween('created_at', [$start, $end]);

        // boards/tasks dùng xoá mềm; users thì không -> chỉ lọc deleted_at cho bảng có cột đó.
        if (in_array($table, ['boards', 'tasks'], true)) {
            $query->whereNull('deleted_at');
        }

        return $query->groupBy('d')
            ->pluck('c', 'd')
            ->all();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function parseRange(?string $range): array
    {
        if (! $range) {
            return [Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay()];
        }
        $parts = explode(' to ', $range);
        $start = Carbon::parse($parts[0])->startOfDay();
        $end = Carbon::parse($parts[1] ?? $parts[0])->endOfDay();

        return [$start, $end];
    }
}
