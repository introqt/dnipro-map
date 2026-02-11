<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'message_id',
        'raw_message',
        'parsed_lat',
        'parsed_lon',
        'parsed_text',
        'keywords',
        'metadata',
        'processed_at',
    ];

    protected $casts = [
        'keywords' => 'array',
        'metadata' => 'array',
        'parsed_lat' => 'double',
        'parsed_lon' => 'double',
        'processed_at' => 'datetime',
    ];
}
