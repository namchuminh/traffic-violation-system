{{-- resources/views/pages/violation-detail.blade.php --}}
@extends('layouts.app')
@section('title', "Chi tiết vi phạm #{$violation->id}")

@section('content')
<style>
    /* CSS chuẩn cho văn bản hành chính */
    .font-admin {
        font-family: "Times New Roman", Times, serif;
    }

    @media print {
        /* Ẩn toàn bộ các phần không liên quan trên web */
        body * { visibility: hidden; }
        
        /* Chỉ hiển thị vùng biên bản */
        #fine-ticket, #fine-ticket * { visibility: visible; }
        
        #fine-ticket {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 0;
            margin: 0;
            background: white;
        }

        /* Loại bỏ header/footer mặc định của trình duyệt */
        @page {
            size: A4;
            margin: 1.5cm;
        }

        .no-print { display: none !important; }
    }
</style>

<div class="space-y-4">
    {{-- Phần hiển thị trên Web --}}
    <div class="grid grid-cols-1 2xl:grid-cols-3 gap-4 no-print">
        <div class="2xl:col-span-2 bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <div class="font-semibold text-slate-800">Bằng chứng vi phạm</div>
                <div class="flex gap-2">
                    @if($violation->handling_status === 'pending')
                        <form action="{{ route('violations.update', $violation->id) }}" method="POST" class="flex gap-2">
                            @csrf
                            <button type="submit" name="action" value="confirm" class="px-4 py-2 rounded-2xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition">Xác nhận</button>
                            <button type="submit" name="action" value="reject" class="px-4 py-2 rounded-2xl bg-rose-600 text-white text-sm font-semibold hover:bg-rose-700 transition">Từ chối</button>
                        </form>
                    @else
                        <span class="px-4 py-2 rounded-2xl bg-slate-100 text-slate-500 text-sm font-semibold border border-slate-200">Đã xử lý (Khóa)</span>
                    @endif
                    <button onclick="window.print()" class="px-4 py-2 rounded-2xl bg-indigo-700 text-white text-sm font-semibold hover:bg-indigo-800 shadow-md transition">
                        Tạo biên bản PDF
                    </button>
                </div>
            </div>
            <div class="p-5 bg-slate-900 flex justify-center border-b">
                <img src="{{ $violation->evidence_image_url }}" class="max-h-[500px] rounded-lg shadow-2xl object-contain">
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6 space-y-6">
            <h3 class="font-bold text-lg text-slate-800 border-b pb-2">Thông tin xử lý</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Loại vi phạm:</span>
                    <span class="font-bold text-rose-600 text-base">
                        @php
                            $types = ['red_light' => 'Vượt đèn đỏ', 'yellow_light' => 'Vượt đèn vàng', 'speeding' => 'Quá tốc độ'];
                            echo $types[$violation->violation_type] ?? 'Vi phạm khác';
                        @endphp
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Trạng thái:</span>
                    <span class="px-3 py-1 rounded-full text-xs font-bold border 
                        {{ $violation->handling_status === 'confirmed' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 
                           ($violation->handling_status === 'rejected' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-sky-50 text-sky-700 border-sky-200') }}">
                        {{ $violation->handling_status === 'pending' ? 'CHỜ XỬ LÝ' : ($violation->handling_status === 'confirmed' ? 'ĐÃ XÁC NHẬN' : 'ĐÃ TỪ CHỐI') }}
                    </span>
                </div>
            </div>

            <form action="{{ route('violations.update', $violation->id) }}" method="POST" class="space-y-3 pt-4 border-t">
                @csrf
                <label class="text-xs font-bold text-slate-400 uppercase tracking-wider">Thông tin phương tiện & Ghi chú</label>
                <textarea name="notes" class="w-full p-4 border border-slate-200 rounded-2xl text-sm outline-none focus:ring-2 focus:ring-indigo-500 transition h-40" 
                    placeholder="Nhập biển số xe (VD: 29A-123.45), chủ sở hữu...">{{ $violation->notes }}</textarea>
                <button type="submit" class="w-full bg-slate-900 text-white py-3 rounded-2xl font-semibold hover:bg-slate-800 transition">Lưu ghi chú</button>
            </form>
        </div>
    </div>

    {{-- MẪU BIÊN BẢN PHẠT NGUỘI (Chỉ hiển thị khi in) --}}
    <div id="fine-ticket" class="hidden print:block font-admin text-black bg-white p-8">
        <div class="flex justify-between items-start mb-10 text-center">
            <div class="w-1/2">
                <p class="font-bold text-sm uppercase">CƠ QUAN CÔNG AN</p>
                <p class="font-bold text-sm uppercase border-b border-black inline-block px-4">HỆ THỐNG GIÁM SÁT AI</p>
                <p class="mt-2 text-xs">Số: {{ $violation->id }}/TB-CSGT</p>
            </div>
            <div class="w-1/2">
                <p class="font-bold text-sm uppercase">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</p>
                <p class="font-bold text-sm border-b border-black inline-block pb-1">Độc lập - Tự do - Hạnh phúc</p>
            </div>
        </div>

        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold uppercase">Thông báo</h1>
            <p class="font-bold text-lg uppercase">Về việc vi phạm quy định trật tự, an toàn giao thông đường bộ</p>
            <p class="italic text-sm">(Được phát hiện qua hệ thống thiết bị ghi hình chuyên dụng)</p>
        </div>

        <div class="text-base space-y-4 text-justify">
            <p>Căn cứ dữ liệu hình ảnh trích xuất từ hệ thống camera giám sát giao thông thông minh vào ngày {{ $violation->created_at->format('d/m/Y') }}.</p>
            
            <p>Cơ quan Công an thông báo hành vi vi phạm của phương tiện có thông tin như sau:</p>
            
            <table class="w-full border-collapse">
                <tr>
                    <td class="py-1 w-44">1. Mã vụ việc:</td>
                    <td class="py-1 font-bold">#{{ $violation->id }}</td>
                </tr>
                <tr>
                    <td class="py-1">2. Thời gian vi phạm:</td>
                    <td class="py-1 font-bold">{{ $violation->created_at->format('H \g\i\ờ i \p\h\ú\t, \n\g\à\y d \t\h\á\n\g m \n\ă\m Y') }}</td>
                </tr>
                <tr>
                    <td class="py-1">3. Địa điểm vi phạm:</td>
                    <td class="py-1 font-bold">{{ $violation->processedVideo->zone->name ?? 'Tuyến đường giám sát' }}</td>
                </tr>
                <tr>
                    <td class="py-1">4. Hành vi vi phạm:</td>
                    <td class="py-1 font-bold text-lg uppercase underline">
                        @php
                            echo $types[$violation->violation_type] ?? 'VI PHẠM QUY ĐỊNH GIAO THÔNG';
                        @endphp
                    </td>
                </tr>
                <tr>
                    <td class="py-1">5. Ghi chú phương tiện:</td>
                    <td class="py-1 font-bold">{{ $violation->notes ?: '..................................................................' }}</td>
                </tr>
            </table>

            <div class="mt-6 border border-black p-2 text-center bg-gray-50">
                <p class="font-bold text-xs uppercase mb-2 italic">Hình ảnh trích xuất làm bằng chứng</p>
                <img src="{{ $violation->evidence_image_url }}" class="max-h-80 mx-auto border border-black">
            </div>

            <p class="mt-6">Yêu cầu chủ phương tiện hoặc người điều khiển phương tiện có mặt tại cơ quan Công an để giải quyết vụ việc theo quy định của pháp luật. Khi đi mang theo các giấy tờ liên quan (Giấy đăng ký xe, Giấy phép lái xe, Căn cước công dân).</p>
        </div>

        <div class="mt-12 flex justify-between items-start">
            <div class="text-center w-1/2 italic">
                <p>Ngày ...... tháng ...... năm ......</p>
                <p class="font-bold not-italic uppercase mt-1 text-base">Người lập thông báo</p>
                <div class="h-28"></div>
                <p class="font-bold not-italic">Hệ thống xử lý tự động</p>
                <p>(Ký, ghi rõ họ tên)</p>
            </div>
            <div class="text-center w-1/2 italic">
                <p>Hà Nội, ngày {{ now()->format('d') }} tháng {{ now()->format('m') }} năm {{ now()->format('Y') }}</p>
                <p class="font-bold not-italic uppercase mt-1 text-base">XÁC THỰC DỮ LIỆU ĐIỆN TỬ</p>
                <div class="h-28"></div>
                <p class="font-bold not-italic uppercase">ĐÃ KIỂM CHỨNG DỮ LIỆU GỐC</p>
                <p class="text-xs">(Trích xuất từ hệ thống giám sát)</p>
            </div>
        </div>
    </div>
</div>
@endsection