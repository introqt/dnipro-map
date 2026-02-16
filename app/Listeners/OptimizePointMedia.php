<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\OptimizePointMediaJob;
use App\Models\Point;

class OptimizePointMedia
{
    public function handle(Point $point): void
    {
        if (! $point->wasRecentlyCreated && ! $point->wasChanged('media')) {
            return;
        }

        if (empty($point->media) || ! is_array($point->media)) {
            return;
        }

        OptimizePointMediaJob::dispatch($point->media, $point->id);
    }
}
