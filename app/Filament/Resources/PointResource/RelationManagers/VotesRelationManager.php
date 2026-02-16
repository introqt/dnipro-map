<?php

namespace App\Filament\Resources\PointResource\RelationManagers;

use App\Enums\VoteType;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VotesRelationManager extends RelationManager
{
    protected static string $relationship = 'votes';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.first_name')
                    ->label('User')
                    ->searchable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof VoteType ? match ($state) {
                        VoteType::Like => 'Like',
                        VoteType::Dislike => 'Dislike',
                    } : (string) $state)
                    ->color(fn ($state): string => $state instanceof VoteType ? match ($state) {
                        VoteType::Like => 'success',
                        VoteType::Dislike => 'danger',
                    } : 'gray'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        VoteType::Like->value => 'Like',
                        VoteType::Dislike->value => 'Dislike',
                    ]),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
