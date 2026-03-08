<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'roboflow_coordinates', 'max_speed'];

    // Ép kiểu dữ liệu cho tọa độ
    protected $casts = [
        'roboflow_coordinates' => 'array',
    ];

    /**
     * Một Zone có nhiều Video đã xử lý
     */
    public function processedVideos()
    {
        return $this->hasMany(ProcessedVideo::class);
    }

    /**
     * Một Zone có nhiều Vi phạm (thông qua Video đã xử lý)
     * Đây là phương thức bạn đang thiếu
     */
    public function violations()
    {
        return $this->hasManyThrough(
            Violation::class, 
            ProcessedVideo::class,
            'zone_id',           // Khóa ngoại trên bảng processed_videos
            'processed_video_id', // Khóa ngoại trên bảng violations
            'id',                // Khóa chính trên bảng zones
            'id'                 // Khóa chính trên bảng processed_videos
        );
    }
}