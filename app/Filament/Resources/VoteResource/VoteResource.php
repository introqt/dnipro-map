<?php

namespace App\Filament\Resources\VoteResource;

use App\Enums\VoteType;
use App\Filament\Resources\VoteResource\Pages;
use App\Models\Vote;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VoteResource extends Resource
{
    protected static ?string $model = Vote::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-hand-thumb-up';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('user.first_name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('point.description')
                    ->label('Point')
                    ->limit(30)
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

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVotes::route('/'),
        ];
    }
}
