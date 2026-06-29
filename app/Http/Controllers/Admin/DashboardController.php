<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        return \Inertia\Inertia::render('Admin/Dashboard');
    }

    public function getUserRegistrations(Request $request)
    {
        try {
            $dateRange = $request->query('date_range');

            if (! $dateRange) {
                return response()->json(['labels' => [], 'datasets' => []]);
            }

            $dates = explode(' to ', $dateRange);
            if (count($dates) === 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
            } else {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = $startDate->copy()->endOfDay();
            }

            $registrations = DB::table('users')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as total'))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $labels = [];
            $data = [];

            $current = $startDate->copy();
            while ($current->lte($endDate)) {
                $label = $current->toDateString();
                $labels[] = $label;

                $entry = $registrations->firstWhere('date', $label);
                $data[] = $entry ? $entry->total : 0;

                $current->addDay();
            }

            return response()->json([
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Số người đăng ký',
                        'data' => $data,
                        'borderColor' => 'rgb(75, 192, 192)',
                        'fill' => false,
                        'tension' => 0.1,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            logger()->error('Chart data error: ' . $e->getMessage());

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
