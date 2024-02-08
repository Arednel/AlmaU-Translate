<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $table = 'videos';

    protected $fillable = [
        'video_path',
        'video_name',
        'current_progress',
        'is_processing',
        'is_translated',
    ];
}
