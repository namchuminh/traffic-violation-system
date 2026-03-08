<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use App\Models\ProcessedVideo;
use Illuminate\Http\Request;

class VideosController extends Controller
{
    public function index(Request $request)
    {
        // 1. Lấy TẤT CẢ dữ liệu zones (bao gồm cả tọa độ polygons) để JavaScript xử lý
        // Cần lấy thêm roboflow_coordinates và max_speed
        $zones = Zone::orderBy('name')->get(['id', 'name', 'roboflow_coordinates', 'max_speed']);

        // 2. Query danh sách video đã xử lý
        $query = ProcessedVideo::with(['zone', 'processedBy']);

        // Lọc theo tên file
        if ($request->filled('search')) {
            $query->where('file_name', 'like', '%' . $request->search . '%');
        }

        // Lọc theo Zone
        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        // Phân trang 10 mục
        $processedVideos = $query->latest()->paginate(10)->appends($request->only('search', 'zone_id'));

        return view('pages.videos', compact('zones', 'processedVideos'));
    }
}