<?php

namespace App\Models;

use App\Enums\PointStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Point extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'description',
        'photo_url',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => PointStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getColorAttribute(): string
    {
        $hoursAgo = $this->created_at->diffInMinutes(now()) / 60;

        return match (true) {
            $hoursAgo <= 1 => 'red',
            $hoursAgo <= 2 => 'yellow',
            $hoursAgo <= 3 => 'green',
            default => 'gray',
        };
    }
}
