<?php

namespace App\Filament\Resources\PointResource\Pages;

use App\Filament\Resources\PointResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePoint extends CreateRecord
{
    protected static string $resource = PointResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['user_id'])) {
            $data['user_id'] = 1;
        }

        return $data;
    }
}
