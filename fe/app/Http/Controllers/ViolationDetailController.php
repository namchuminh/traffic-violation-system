<?php

namespace App\Http\Controllers;

use App\Models\Violation;
use Illuminate\Http\Request;

class ViolationDetailController extends Controller
{
    public function show($id)
    {
        // Lấy dữ liệu vi phạm cùng với thông tin video liên quan
        $violation = Violation::with('processedVideo.zone')->findOrFail($id);
        
        return view('pages.violation-detail', compact('violation'));
    }

    // App/Http/Controllers/ViolationDetailController.php

    public function update(Request $request, $id)
    {
        $violation = Violation::findOrFail($id);

        // Yêu cầu 2: Nếu đã xác nhận hoặc từ chối thì không cho sửa lại
        if ($violation->handling_status !== 'pending') {
            return back()->with('error', 'Vi phạm này đã được xử lý trước đó, không thể thay đổi trạng thái.');
        }

        if ($request->action === 'confirm') {
            $violation->handling_status = 'confirmed';
        } elseif ($request->action === 'reject') {
            $violation->handling_status = 'rejected';
        }

        if ($request->has('notes')) {
            $violation->notes = $request->notes;
        }

        $violation->save();

        return back()->with('success', 'Đã cập nhật trạng thái vi phạm thành công!');
    }
}