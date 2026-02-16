<?php

namespace App\Filament\Resources\ChannelMessageResource\Pages;

use App\Filament\Resources\ChannelMessageResource\ChannelMessageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewChannelMessage extends ViewRecord
{
    protected static string $resource = ChannelMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
