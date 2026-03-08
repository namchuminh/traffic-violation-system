<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    /**
     * Xử lý yêu cầu đến dựa trên vai trò người dùng.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles Danh sách các vai trò được phép truy cập
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // 1. Kiểm tra người dùng đã đăng nhập chưa
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // 2. Kiểm tra role của user có nằm trong danh sách các quyền được phép không
        // Sử dụng spread operator (...) để nhận nhiều tham số từ Route
        if (in_array(auth()->user()->role, $roles)) {
            return $next($request);
        }

        // 3. Nếu không có quyền, ngắt luồng và trả về lỗi 403 (Forbidden)
        // Không dùng 'return abort' để tránh lỗi PHP1409
        abort(403, 'Bạn không có quyền truy cập vào chức năng này.');
    }
}