<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProcessedVideo extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'processed_video_url',
        'zone_id',
        'count_direction_a',
        'count_direction_b',
        'processing_time_ms',
        'processed_by',
    ];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function violations()
    {
        return $this->hasMany(Violation::class);
    }
}