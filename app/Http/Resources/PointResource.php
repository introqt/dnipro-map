<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Point */
class PointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'description' => $this->description,
            'photo_url' => $this->photo_url,
            'color' => $this->color,
            'created_at' => $this->created_at->toIso8601String(),
            'user' => [
                'first_name' => $this->user->first_name,
            ],
        ];
    }
}
