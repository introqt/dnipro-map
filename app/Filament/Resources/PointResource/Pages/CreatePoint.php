<?php

namespace App\Filament\Resources\PointResource\Pages;

use App\Filament\Resources\PointResource\PointResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePoint extends CreateRecord
{
    protected static string $resource = PointResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set the current authenticated user as the point creator
        $data['user_id'] = auth()->id();

        return $data;
    }
}
