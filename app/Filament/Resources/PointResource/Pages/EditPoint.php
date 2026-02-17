<?php

namespace App\Filament\Resources\PointResource\Pages;

use App\Filament\Resources\PointResource\Actions\ApprovePointAction;
use App\Filament\Resources\PointResource\Actions\RejectPointAction;
use App\Filament\Resources\PointResource\PointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPoint extends EditRecord
{
    protected static string $resource = PointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ApprovePointAction::make(),
            RejectPointAction::make(),
            DeleteAction::make(),
        ];
    }
}
