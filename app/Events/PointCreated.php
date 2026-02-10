<?php

namespace App\Events;

use App\Models\Point;
use Illuminate\Foundation\Events\Dispatchable;

class PointCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Point $point
    ) {}
}
