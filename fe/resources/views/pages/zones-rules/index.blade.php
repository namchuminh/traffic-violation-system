@extends('layouts.app')
@section('title','Vùng & Luật')
@section('breadcrumb','Vùng & Luật')
@section('page_title','Quản lý vùng')

@section('content')
<div class="space-y-4">
    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="font-semibold">Danh sách vùng</div>
                <div class="text-sm text-slate-500">Thêm / sửa / xoá vùng, lưu tọa độ Roboflow</div>
            </div>

            <div class="flex items-center gap-2">
                <form method="GET" action="{{ route('zones-rules.index') }}" class="flex items-center gap-2">
                    <input name="q" value="{{ $q }}"
                           placeholder="Tìm theo tên vùng..."
                           class="w-64 px-4 py-2.5 rounded-2xl border border-slate-200 bg-white outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-300 text-sm" />
                    <button class="px-3 py-2.5 rounded-2xl border border-slate-200 text-sm hover:bg-slate-50">
                        Tìm
                    </button>
                </form>

                <a href="{{ route('zones-rules.create') }}"
                   class="px-3 py-2.5 rounded-2xl bg-indigo-700 text-white text-sm hover:bg-indigo-800">
                    Thêm vùng
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="px-5 py-3 text-sm bg-emerald-50 text-emerald-700 border-b border-emerald-100">
                {{ session('success') }}
            </div>
        @endif

        <div class="p-5 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-slate-500">
                <tr class="border-b border-slate-200">
                    <th class="text-left py-3 pr-3">Tên vùng</th>
                    <th class="text-left py-3 pr-3">Tốc độ tối đa</th>
                    <th class="text-left py-3 pr-3">Cập nhật</th>
                    <th class="text-right py-3">Thao tác</th>
                </tr>
                </thead>
                <tbody>
                @forelse($zones as $zone)
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="py-4 pr-3">
                            <div class="font-semibold">{{ $zone->name }}</div>
                            <details class="mt-1">
                                <summary class="text-xs text-indigo-700 cursor-pointer">Xem tọa độ</summary>
                                <pre class="mt-2 text-xs font-mono bg-white border border-slate-200 rounded-2xl p-3 overflow-auto max-h-44">{{ json_encode($zone->roboflow_coordinates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        </td>
                        <td class="py-4 pr-3 text-slate-600">
                            {{ $zone->max_speed ? ($zone->max_speed . ' km/h') : 'Không xác định' }}
                        </td>

                        <td class="py-4 pr-3 text-slate-600">
                            {{ optional($zone->updated_at)->format('Y-m-d H:i') }}
                        </td>

                        <td class="py-4 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('zones-rules.edit', $zone) }}"
                                   class="px-3 py-2 rounded-2xl border border-slate-200 text-sm hover:bg-slate-50">
                                    Sửa
                                </a>

                                <form method="POST" action="{{ route('zones-rules.destroy', $zone) }}"
                                      onsubmit="return confirm('Xoá vùng này?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="px-3 py-2 rounded-2xl bg-rose-600 text-white text-sm hover:bg-rose-700">
                                        Xoá
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="py-6 text-slate-500" colspan="3">Không có dữ liệu.</td></tr>
                @endforelse
                </tbody>
            </table>

            <div class="mt-4">
                {{ $zones->links() }}
            </div>
        </div>
    </div>
</div>
@endsection