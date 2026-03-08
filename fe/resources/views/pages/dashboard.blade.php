{{-- resources/views/pages/dashboard.blade.php --}}
@extends('layouts.app')
@section('title', 'Bảng điều khiển')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 uppercase">Thống kê tổng quan</h2>
        <p class="text-sm text-slate-500 font-medium italic">Tháng {{ Carbon\Carbon::parse($filterMonth)->format('m/Y') }}</p>
    </div>
    <form action="{{ route('dashboard') }}" method="GET">
        <input type="month" name="month" value="{{ $filterMonth }}" onchange="this.form.submit()"
            class="px-4 py-2 rounded-2xl border border-slate-200 bg-white text-sm font-semibold outline-none focus:ring-4 focus:ring-indigo-100 cursor-pointer transition-all">
    </form>
</div>

{{-- KPI Cards --}}
<div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm transition-transform hover:scale-[1.02]">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Lưu lượng phương tiện</div>
        <div class="mt-2 text-3xl font-extrabold text-indigo-600">{{ number_format($totalTraffic) }}</div>
        <div class="text-[10px] mt-1 text-slate-400 italic">Tổng lượt xe qua các Zone</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm transition-transform hover:scale-[1.02]">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tổng vụ vi phạm</div>
        <div class="mt-2 text-3xl font-extrabold text-slate-800">{{ number_format($totalViolations) }}</div>
        <div class="text-[10px] mt-1 text-slate-400 italic">Dữ liệu ghi nhận trong tháng</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm border-l-4 border-l-rose-500 transition-transform hover:scale-[1.02]">
        <div class="text-xs font-bold text-rose-500 uppercase tracking-wider">Vi phạm mới</div>
        <div class="mt-2 text-3xl font-extrabold text-rose-600">{{ number_format($newViolations) }}</div>
        <div class="text-[10px] mt-1 text-slate-400 italic">Đang chờ xử lý/phê duyệt</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm transition-transform hover:scale-[1.02]">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tỷ lệ vi phạm</div>
        <div class="mt-2 text-3xl font-extrabold text-emerald-600">
            {{ $totalTraffic > 0 ? number_format(($totalViolations / $totalTraffic) * 100, 2) : 0 }}%
        </div>
        <div class="text-[10px] mt-1 text-slate-400 italic">Hiệu suất an toàn giao thông</div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-6">
    <div class="xl:col-span-2 space-y-6">
        {{-- Biểu đồ Vi phạm --}}
        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-slate-700 uppercase text-sm tracking-wide">Số vụ vi phạm theo ngày</h3>
                <span class="text-[10px] font-bold px-2 py-0.5 bg-rose-50 text-rose-600 rounded-lg">BIỂN ĐỘNG THÁNG</span>
            </div>
            <div class="h-64"><canvas id="violationChart"></canvas></div>
        </div>

        {{-- Biểu đồ Lưu lượng --}}
        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-slate-700 uppercase text-sm tracking-wide">Lưu lượng phương tiện theo ngày</h3>
                <span class="text-[10px] font-bold px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-lg">DỮ LIỆU CAMERA</span>
            </div>
            <div class="h-64"><canvas id="trafficChart"></canvas></div>
        </div>
    </div>

    <div class="space-y-6">
        {{-- Biểu đồ Phân loại --}}
        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm flex flex-col">
            <h3 class="font-bold text-slate-700 uppercase text-sm tracking-wide mb-4">Cơ cấu loại vi phạm</h3>
            <div class="h-64 flex justify-center"><canvas id="typePieChart"></canvas></div>
            <div class="mt-6 space-y-3">
                @foreach($typeStats as $s)
                    <div class="flex items-center justify-between text-sm">
                        <span class="inline-flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ $s->violation_type == 'speeding' ? 'bg-indigo-500' : ($s->violation_type == 'red_light' ? 'bg-rose-500' : 'bg-amber-500') }}"></span> 
                            {{ $s->violation_type == 'speeding' ? 'Quá tốc độ' : ($s->violation_type == 'red_light' ? 'Vượt đèn đỏ' : 'Vượt đèn vàng') }}
                        </span>
                        <span class="font-bold text-slate-700">{{ number_format($s->count) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Top Zone Vi phạm --}}
        <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
            <h3 class="font-bold text-slate-700 uppercase text-xs mb-4">Vi phạm theo Khu vực (Zone)</h3>
            <div class="space-y-4">
                @forelse($zoneViolations as $zv)
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-slate-600 italic">{{ $zv->name }}</span>
                    <span class="text-xs font-bold text-rose-600 bg-rose-50 px-2.5 py-1 rounded-xl border border-rose-100">{{ $zv->violations_count }} vi phạm</span>
                </div>
                @empty
                <p class="text-xs text-slate-400 italic text-center py-2">Chưa ghi nhận vi phạm.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Bảng lưu lượng chi tiết --}}
<div class="bg-white border border-slate-200 rounded-3xl mt-6 shadow-sm overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50 flex justify-between items-center">
        <h3 class="font-bold text-slate-700 uppercase text-xs tracking-widest">Lưu lượng chi tiết theo hướng</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left font-medium">
            <thead class="bg-slate-50 text-slate-400 uppercase text-[10px] tracking-tighter">
                <tr>
                    <th class="px-6 py-4">Tên Khu Vực (Zone)</th>
                    <th class="px-6 py-4">Chiều A (Vào)</th>
                    <th class="px-6 py-4">Chiều B (Ra)</th>
                    <th class="px-6 py-4 text-right">Tổng Lưu Lượng</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($zoneTraffic as $zt)
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="px-6 py-4 text-slate-800 font-bold italic">{{ $zt->name }}</td>
                    <td class="px-6 py-4 text-slate-500 font-mono">{{ number_format($zt->total_a) }}</td>
                    <td class="px-6 py-4 text-slate-500 font-mono">{{ number_format($zt->total_b) }}</td>
                    <td class="px-6 py-4 text-right font-extrabold text-indigo-700">{{ number_format($zt->total_all) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<script>
    const chartConfig = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f8fafc' }, ticks: { font: { size: 9 } } },
            x: { grid: { display: false }, ticks: { font: { size: 9 } } }
        }
    };

    // Biểu đồ Vi phạm
    new Chart(document.getElementById('violationChart'), {
        type: 'line',
        data: {
            labels: @json($labels),
            datasets: [{
                data: @json($lineChartViolations),
                borderColor: '#f43f5e',
                backgroundColor: 'rgba(244, 63, 94, 0.05)',
                fill: true,
                tension: 0.4,
                borderWidth: 2.5,
                pointRadius: 2
            }]
        },
        options: chartConfig
    });

    // Biểu đồ Lưu lượng
    new Chart(document.getElementById('trafficChart'), {
        type: 'line',
        data: {
            labels: @json($labels),
            datasets: [{
                data: @json($lineChartTraffic),
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.05)',
                fill: true,
                tension: 0.4,
                borderWidth: 2.5,
                pointRadius: 2
            }]
        },
        options: chartConfig
    });

    // Biểu đồ Tròn
    new Chart(document.getElementById('typePieChart'), {
        type: 'doughnut',
        data: {
            labels: @json($typeStats->pluck('violation_type')->map(fn($t) => $t == 'speeding' ? 'Quá tốc độ' : ($t == 'red_light' ? 'Vượt đèn đỏ' : 'Vượt đèn vàng'))),
            datasets: [{
                data: @json($typeStats->pluck('count')),
                backgroundColor: ['#6366f1', '#f43f5e', '#fbbf24'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: { cutout: '80%', plugins: { legend: { display: false } } }
    });
</script>
@endsection