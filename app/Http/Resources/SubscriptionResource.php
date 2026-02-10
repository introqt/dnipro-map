<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Subscription */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'radius_km' => $this->radius_km,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
