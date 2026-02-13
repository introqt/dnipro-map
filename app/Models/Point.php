<?php

namespace App\Models;

use App\Enums\PointStatus;
use App\Enums\PointType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
        'type',
        'moderated_by',
        'moderated_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => PointStatus::class,
            'type' => PointType::class,
            'moderated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function isPending(): bool
    {
        return $this->status === PointStatus::Pending;
    }

    public function isActive(): bool
    {
        return $this->status === PointStatus::Active;
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
