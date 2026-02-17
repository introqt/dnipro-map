<?php

namespace App\Filament\Resources\PointResource;

use App\Enums\PointStatus;
use App\Filament\Resources\PointResource\Pages\EditPoint;
use App\Filament\Resources\PointResource\Pages\ListPoints;
use App\Filament\Resources\PointResource\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\PointResource\RelationManagers\VotesRelationManager;
use App\Filament\Resources\PointResource\Schemas\PointForm;
use App\Filament\Resources\PointResource\Tables\PointTable;
use App\Models\Point;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PointResource extends Resource
{
    protected static ?string $model = Point::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $pendingCount = Point::where('status', PointStatus::Pending)->count();

        return $pendingCount > 0 ? (string) $pendingCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return PointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PointTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CommentsRelationManager::class,
            VotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPoints::route('/'),
            'edit' => EditPoint::route('/{record}/edit'),
        ];
    }
}
