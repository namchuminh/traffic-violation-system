{{-- resources/views/pages/violations.blade.php --}}
@extends('layouts.app')
@section('title', 'Vi phạm')
@section('breadcrumb', 'Vi phạm')
@section('page_title', 'Danh sách vi phạm')

@section('content')
    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="font-semibold">Vi phạm</div>
                    <div class="text-sm text-slate-500">Duyệt, xác nhận, xuất biên bản</div>
                </div>
            </div>

            {{-- Form Lọc và Tìm kiếm --}}
            <form action="{{ route('violations.index') }}" method="GET" class="mt-4 flex flex-wrap gap-2">
                {{-- Tìm kiếm --}}
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Mã video (#)..."
                    class="w-full sm:w-64 px-3 py-2 rounded-2xl border border-slate-200 bg-white text-sm outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-300 placeholder:text-slate-400" />

                {{-- Loại vi phạm --}}
                <select name="type" class="px-3 py-2 rounded-2xl border border-slate-200 bg-white text-sm outline-none">
                    <option value="all">Tất cả loại</option>
                    <option value="speeding" {{ request('type') == 'speeding' ? 'selected' : '' }}>Quá tốc độ</option>
                    <option value="red_light" {{ request('type') == 'red_light' ? 'selected' : '' }}>Vượt đèn đỏ</option>
                    <option value="yellow_light" {{ request('type') == 'yellow_light' ? 'selected' : '' }}>Vượt đèn vàng
                    </option>
                </select>

                {{-- Trạng thái --}}
                <select name="status" class="px-3 py-2 rounded-2xl border border-slate-200 bg-white text-sm outline-none">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="new" {{ request('status') == 'new' ? 'selected' : '' }}>Mới (Chờ xử lý)</option>
                    <option value="handled" {{ request('status') == 'handled' ? 'selected' : '' }}>Đã xử lý</option>
                </select>

                {{-- Lọc ngày --}}
                <input type="date" name="date" value="{{ request('date') }}"
                    class="px-3 py-2 rounded-2xl border border-slate-200 bg-white text-sm outline-none" />

                <div class="flex gap-2">
                    <button type="submit"
                        class="px-4 py-2 rounded-2xl bg-slate-800 text-white text-sm font-semibold hover:bg-slate-700">
                        Lọc
                    </button>
                    @if(request()->anyFilled(['search', 'type', 'status', 'date']))
                        <a href="{{ route('violations.index') }}"
                            class="px-4 py-2 rounded-2xl border border-slate-200 text-sm hover:bg-slate-50 flex items-center">
                            Xóa lọc
                        </a>
                    @endif
                </div>

                <div class="flex gap-2">
                    {{-- Nút Xuất Excel mới --}}
                    <a href="{{ route('violations.export', request()->all()) }}"
                        class="px-4 py-2 rounded-2xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                            </path>
                        </svg>
                        Xuất Excel
                    </a>
                </div>
            </form>
        </div>

        <div class="p-5 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-slate-500">
                    <tr class="border-b border-slate-200 text-left">
                        <th class="py-3 pr-3">Thời gian</th>
                        <th class="py-3 pr-3">Bằng chứng</th> {{-- Cột mới --}}
                        <th class="py-3 pr-3">Loại vi phạm</th>
                        <th class="py-3 pr-3">Video nguồn</th>
                        <th class="py-3 pr-3">Trạng thái</th>
                        <th class="text-right py-3">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($violations as $v)
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
                            <td class="py-4 pr-3 text-slate-600">
                                {{ $v->created_at->format('d/m/Y') }}
                                <div class="text-[11px] text-slate-400">{{ $v->created_at->format('H:i:s') }}</div>
                            </td>

                            {{-- Cột Bằng chứng --}}
                            <td class="py-4 pr-3">
                                <div
                                    class="relative w-16 h-10 rounded-lg overflow-hidden border border-slate-200 bg-slate-100 group cursor-zoom-in">
                                    @if($v->evidence_image_url)
                                        <img src="{{ $v->evidence_image_url }}" class="w-full h-full object-cover img-evidence"
                                            role="button">
                                    @else
                                        <div class="flex items-center justify-center h-full text-slate-300">N/A</div>
                                    @endif
                                </div>
                            </td>

                            <td class="py-4 pr-3">
                                @php
                                    $typeMap = [
                                        'red_light' => ['Vượt đèn đỏ', 'rose'],
                                        'yellow_light' => ['Vượt đèn vàng', 'amber'],
                                        'speeding' => ['Quá tốc độ', 'indigo'],
                                    ];
                                    $typeInfo = $typeMap[$v->violation_type] ?? [$v->violation_type, 'slate'];
                                @endphp
                                <span class="text-xs px-2.5 py-1 rounded-full border 
                                        {{ $typeInfo[1] === 'rose' ? 'bg-rose-50 text-rose-700 border-rose-200' : '' }}
                                        {{ $typeInfo[1] === 'amber' ? 'bg-amber-50 text-amber-700 border-amber-200' : '' }}
                                        {{ $typeInfo[1] === 'indigo' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : '' }}
                                        {{ $typeInfo[1] === 'slate' ? 'bg-slate-100 text-slate-700 border-slate-200' : '' }}">
                                    {{ $typeInfo[0] }}
                                </span>
                            </td>

                            <td class="py-4 pr-3 font-mono text-slate-800">#{{ $v->processed_video_id }}</td>

                            <td class="py-4 pr-3">
                                <span
                                    class="text-xs px-2.5 py-1 rounded-full border
                                        {{ $v->handling_status === 'pending' ? 'bg-sky-50 text-sky-700 border-sky-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200' }}">
                                    {{ $v->handling_status === 'pending' ? 'Chờ xử lý' : 'Đã xử lý' }}
                                </span>
                            </td>

                            <td class="py-4 text-right">
                                <a href="{{ route('violations.show', $v->id) }}"
                                    class="px-4 py-2 rounded-2xl border border-slate-200 text-sm hover:bg-slate-50 hover:border-slate-300 transition inline-block">
                                    Chi tiết
                                </a>
                            </td>
                        </tr>
                    @empty
                        {{-- ... --}}
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Phân trang --}}
        <div class="px-5 py-4 border-t border-slate-50 bg-slate-50/50">
            {{ $violations->links() }}
        </div>
    </div>

    <div id="imageModal"
        class="fixed inset-0 z-[9999] hidden bg-black/90 flex items-center justify-center p-4 cursor-zoom-out">
        <button class="absolute top-5 right-5 text-white hover:text-slate-300">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
        <img id="modalImage" src="" class="max-w-full max-h-full rounded-lg shadow-2xl">
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');

            // Bắt sự kiện click vào các ảnh có class .img-evidence
            document.querySelectorAll('.img-evidence').forEach(img => {
                img.onclick = function () {
                    modal.classList.remove('hidden');
                    modalImg.src = this.src;
                    document.body.style.overflow = 'hidden'; // Chặn cuộn trang khi đang xem ảnh
                }
            });

            // Đóng modal khi click ra ngoài hoặc vào nút đóng
            modal.onclick = function () {
                modal.classList.add('hidden');
                modalImg.src = '';
                document.body.style.overflow = ''; // Cho phép cuộn lại
            };
        });
    </script>

@endsection