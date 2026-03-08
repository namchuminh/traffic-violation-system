<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Violation extends Model
{
    use HasFactory;

    protected $fillable = [
        'violation_type',
        'evidence_image_url',
        'processed_video_id',
        'status',
        'handling_status',
        'notes',
    ];

    public function processedVideo()
    {
        return $this->belongsTo(ProcessedVideo::class);
    }
}