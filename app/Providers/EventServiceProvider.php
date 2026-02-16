<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\OptimizePointMedia;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        'eloquent.created: App\\Models\\Point' => [
            OptimizePointMedia::class,
        ],
        'eloquent.updated: App\\Models\\Point' => [
            OptimizePointMedia::class,
        ],
    ];
}
