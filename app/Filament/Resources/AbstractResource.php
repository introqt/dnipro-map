<?php

declare(strict_types= 1);

namespace App\Filament\Resources;

use App\Filament\Resources\PointResource\Pages\CreatePoint;
use App\Filament\Resources\PointResource\Pages\EditPoint;
use App\Filament\Resources\PointResource\Pages\ListPoints;
use App\Filament\Resources\PointResource\Pages\ViewPoint;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class AbstractResource extends Resource
{
    public static function table(Table $table): Table
    {
        return $table->removeActions([EditAction::make()]);
    }


    public static function getPages(): array
    {
        return [
            'index' => ListPoints::route('/'),
            'create' => CreatePoint::route('/create'),
            'view' => ViewPoint::route('/{record}/view'),
            'edit' => EditPoint::route('/{record}/edit'),
        ];
    }
}