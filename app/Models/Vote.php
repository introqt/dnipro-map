<?php

namespace App\Models;

use App\Enums\VoteType;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'point_id',
        'user_id',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'type' => VoteType::class,
        ];
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(Point::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
