<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    protected $fillable = [
        'telegram_id',
        'first_name',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'telegram_id' => 'integer',
            'role' => UserRole::class,
        ];
    }

    public function points(): HasMany
    {
        return $this->hasMany(Point::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}
