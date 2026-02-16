<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Point;
use App\Services\MediaOptimizer;
use Illuminate\Support\Facades\Log;

class OptimizePointMedia
{
    public function __construct(private readonly MediaOptimizer $mediaOptimizer)
    {
    }

    public function handle(Point $point): void
    {
        if (! $point->wasRecentlyCreated && ! $point->wasChanged('media')) {
            return;
        }

        if (empty($point->media) || ! is_array($point->media)) {
            return;
        }

        Log::info('Optimizing media for point', ['point_id' => $point->id, 'media_count' => count($point->media)]);
        $media = $point->media;

        dispatch(function () use ($media) {
            app(MediaOptimizer::class)->optimizeMedia($media);
        })->afterResponse();
    }
}
