<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\MediaOptimizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class OptimizePointMediaJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * @param  array<int, string>  $mediaPaths
     */
    public function __construct(
        private readonly array $mediaPaths,
        private readonly int $pointId,
    ) {
    }

    public function handle(MediaOptimizer $mediaOptimizer): void
    {
        Log::info('Starting queued media optimization', [
            'point_id' => $this->pointId,
            'media_count' => count($this->mediaPaths),
        ]);

        $mediaOptimizer->optimizeMedia($this->mediaPaths);
    }
}
