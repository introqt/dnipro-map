<?php

namespace App\Filament\Resources;

use App\Enums\PointStatus;
use App\Enums\PointType;
use App\Filament\Resources\PointResource\Pages;
use App\Filament\Resources\PointResource\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\PointResource\RelationManagers\VotesRelationManager;
use App\Models\Point;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
        return $schema->components([
            Section::make('Location')
                ->schema([
                    TextInput::make('latitude')
                        ->required()
                        ->numeric()
                        ->minValue(-90)
                        ->maxValue(90)
                        ->step(0.0000001),

                    TextInput::make('longitude')
                        ->required()
                        ->numeric()
                        ->minValue(-180)
                        ->maxValue(180)
                        ->step(0.0000001),
                ])
                ->columns(2),

            Section::make('Details')
                ->schema([
                    Textarea::make('description')
                        ->required()
                        ->maxLength(1000)
                        ->rows(3),

                    Select::make('type')
                        ->options(collect(PointType::cases())->mapWithKeys(
                            fn (PointType $type): array => [$type->value => $type->label()]
                        ))
                        ->required(),

                    Select::make('status')
                        ->options(collect(PointStatus::cases())->mapWithKeys(
                            fn (PointStatus $status): array => [$status->value => $status->label()]
                        ))
                        ->required(),

                    TextInput::make('photo_url')
                        ->label('Photo URL')
                        ->url()
                        ->maxLength(255),
                ])
                ->columns(2),

            Section::make('Moderation')
                ->schema([
                    TextInput::make('rejection_reason')
                        ->maxLength(255),
                ])
                ->collapsed()
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('user.first_name')
                    ->label('Author')
                    ->searchable(),

                TextColumn::make('description')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof PointType ? $state->label() : (string) $state)
                    ->color(fn ($state): string => $state instanceof PointType ? $state->color() : 'gray'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof PointStatus ? $state->label() : (string) $state)
                    ->color(fn ($state): string => $state instanceof PointStatus ? $state->color() : 'gray'),

                TextColumn::make('latitude')
                    ->numeric(7)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('longitude')
                    ->numeric(7)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('moderator.first_name')
                    ->label('Moderated By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('comments_count')
                    ->counts('comments')
                    ->label('Comments')
                    ->sortable(),

                TextColumn::make('votes_count')
                    ->counts('votes')
                    ->label('Votes')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PointStatus::cases())->mapWithKeys(
                        fn (PointStatus $status): array => [$status->value => $status->label()]
                    )),

                SelectFilter::make('type')
                    ->options(collect(PointType::cases())->mapWithKeys(
                        fn (PointType $type): array => [$type->value => $type->label()]
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListPoints::route('/'),
            'create' => Pages\CreatePoint::route('/create'),
            'view' => Pages\ViewPoint::route('/{record}'),
            'edit' => Pages\EditPoint::route('/{record}/edit'),
        ];
    }
}
