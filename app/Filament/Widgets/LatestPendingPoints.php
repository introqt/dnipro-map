<?php

namespace App\Filament\Widgets;

use App\Enums\PointStatus;
use App\Filament\Resources\PointResource\PointResource;
use App\Models\Point;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestPendingPoints extends TableWidget
{
    protected static ?string $heading = 'Latest Pending Points';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'half';

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('view_all')
                ->label('View All')
                ->url(PointResource::getUrl('index') . '?tableFilters[status]=' . PointStatus::Pending->value)
                ->icon('heroicon-m-arrow-right'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Point::query()
                    ->where('status', PointStatus::Pending)
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('description')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('user.first_name')
                    ->label('Author'),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label()),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
