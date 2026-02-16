<?php

namespace App\Models;

use App\Enums\PointStatus;
use App\Enums\PointType;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Point extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'description',
        'media',
        'status',
        'type',
        'moderated_by',
        'moderated_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'string:7',
            'longitude' => 'string:7',
            'media' => 'array',
            'status' => PointStatus::class,
            'type' => PointType::class,
            'moderated_at' => 'datetime',
        ];
    }

    public function location(): Attribute
    {
        return Attribute::make(
            get: fn (): string => "({$this->latitude}, {$this->longitude})",
            set: function(string $value): void {
                $coordinates = explode(',', trim($value, '()'));

                if (count($coordinates) === 2) {
                    $this->attributes['latitude'] = $coordinates[0];
                    $this->attributes['longitude'] = $coordinates[1];
                }
            }
        );
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
