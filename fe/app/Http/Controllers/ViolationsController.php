<?php

namespace App\Http\Controllers;

use App\Models\Violation;
use Illuminate\Http\Request;

class ViolationsController extends Controller
{
    public function index(Request $request)
    {
        // Khởi tạo query với quan hệ processedVideo
        $query = Violation::with('processedVideo');

        // 1. Tìm kiếm theo Mã video xử lý (ID)
        if ($request->filled('search')) {
            $query->where('processed_video_id', 'like', '%' . $request->search . '%');
        }

        // 2. Lọc theo Loại vi phạm
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('violation_type', $request->type);
        }

        // 3. Lọc theo Trạng thái xử lý
        if ($request->filled('status') && $request->status !== 'all') {
            // Map giá trị từ giao diện sang database (handling_status)
            $statusValue = ($request->status === 'new') ? 'pending' : 'handled';
            $query->where('handling_status', $statusValue);
        }

        // 4. Lọc theo khoảng ngày (Nếu input có định dạng chuẩn YYYY-MM-DD)
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // Thực hiện phân trang và giữ lại các tham số lọc trên URL
        $violations = $query->latest()->paginate(10)->appends($request->query());

        return view('pages.violations', compact('violations'));
    }

    public function export(Request $request)
    {
        $fileName = 'danh-sach-vi-pham-' . date('Ymd-His') . '.csv';

        // Lấy dữ liệu dựa trên bộ lọc hiện tại (giống hàm index)
        $query = Violation::query();

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('violation_type', $request->type);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $statusValue = ($request->status === 'new') ? 'pending' : 'handled';
            $query->where('handling_status', $statusValue);
        }

        $violations = $query->latest()->get();

        $headers = [
            "Content-type" => "text/csv; charset=utf-8",
            "Content-Disposition" => "attachment; filename=$fileName",
        ];

        // Thêm cột Link bằng chứng vào tiêu đề
        $columns = ['ID', 'Thời gian', 'Loại vi phạm', 'Mã video xử lý', 'Trạng thái', 'Link bằng chứng'];

        $callback = function () use ($violations, $columns) {
            $file = fopen('php://output', 'w');
            fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
            fputcsv($file, $columns);

            foreach ($violations as $v) {
                fputcsv($file, [
                    $v->id,
                    $v->created_at,
                    $v->violation_type, // Bạn có thể dùng hàm map tên tiếng Việt ở đây
                    $v->processed_video_id,
                    $v->handling_status,
                    $v->evidence_image_url ?: 'Không có ảnh' // Thêm link ảnh ở đây
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
