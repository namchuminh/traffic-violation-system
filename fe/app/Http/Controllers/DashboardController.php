<?php

namespace App\Http\Controllers;

use App\Models\Violation;
use App\Models\ProcessedVideo;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Lấy tháng lọc, mặc định là tháng hiện tại
        $filterMonth = $request->get('month', date('Y-m'));
        $date = Carbon::parse($filterMonth . '-01');

        // 1. Thống kê KPI
        $totalViolations = Violation::whereMonth('created_at', $date->month)->whereYear('created_at', $date->year)->count();
        $newViolations = Violation::whereMonth('created_at', $date->month)->whereYear('created_at', $date->year)
            ->where('handling_status', 'pending')->count();
        $totalTraffic = ProcessedVideo::whereMonth('created_at', $date->month)->whereYear('created_at', $date->year)
            ->sum(DB::raw('count_direction_a + count_direction_b'));

        // 2. Dữ liệu biểu đồ (Ngày trong tháng)
        $daysInMonth = $date->daysInMonth;
        $labels = [];
        $lineChartViolations = [];
        $lineChartTraffic = [];

        $violationByDay = Violation::select(DB::raw('DAY(created_at) as day'), DB::raw('count(*) as count'))
            ->whereMonth('created_at', $date->month)->whereYear('created_at', $date->year)
            ->groupBy('day')->pluck('count', 'day');

        $trafficByDay = ProcessedVideo::select(DB::raw('DAY(created_at) as day'), DB::raw('SUM(count_direction_a + count_direction_b) as total'))
            ->whereMonth('created_at', $date->month)->whereYear('created_at', $date->year)
            ->groupBy('day')->pluck('total', 'day');

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $labels[] = $i . '/' . $date->month;
            $lineChartViolations[] = $violationByDay[$i] ?? 0;
            $lineChartTraffic[] = $trafficByDay[$i] ?? 0;
        }

        // 3. Phân loại vi phạm theo Zone
        $zoneViolations = Zone::withCount(['violations' => function ($query) use ($date) {
            // Chỉ rõ bảng violations để tránh xung đột với bảng trung gian processed_videos
            $query->whereMonth('violations.created_at', $date->month)
                ->whereYear('violations.created_at', $date->year);
        }])->having('violations_count', '>', 0)->get();

        // 4. Lưu lượng theo Zone (Chiều A, B và Tổng)
        $zoneTraffic = Zone::select('zones.id', 'zones.name')
            ->join('processed_videos', 'zones.id', '=', 'processed_videos.zone_id')
            ->whereMonth('processed_videos.created_at', $date->month)
            ->whereYear('processed_videos.created_at', $date->year)
            ->selectRaw('SUM(count_direction_a) as total_a, SUM(count_direction_b) as total_b, SUM(count_direction_a + count_direction_b) as total_all')
            ->groupBy('zones.id', 'zones.name')
            ->orderBy('total_all', 'desc')
            ->get();

        // 5. Thống kê loại vi phạm cho biểu đồ tròn
        $typeStats = Violation::whereMonth('created_at', $date->month)->whereYear('created_at', $date->year)
            ->select('violation_type', DB::raw('count(*) as count'))
            ->groupBy('violation_type')->get();

        return view('pages.dashboard', compact(
            'totalViolations', 'newViolations', 'totalTraffic', 'filterMonth',
            'labels', 'lineChartViolations', 'lineChartTraffic', 
            'typeStats', 'zoneViolations', 'zoneTraffic'
        ));
    }
}