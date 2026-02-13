<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Activity Logs';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Event')
                ->schema([
                    TextInput::make('action')
                        ->disabled(),

                    TextInput::make('description')
                        ->disabled(),

                    TextInput::make('user.first_name')
                        ->label('User')
                        ->disabled(),

                    TextInput::make('subject_type')
                        ->label('Subject Type')
                        ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-')
                        ->disabled(),

                    TextInput::make('subject_id')
                        ->label('Subject ID')
                        ->disabled(),
                ])
                ->columns(2),

            Section::make('Properties')
                ->schema([
                    KeyValue::make('properties')
                        ->disabled(),
                ]),

            Section::make('Request Info')
                ->schema([
                    TextInput::make('ip_address')
                        ->label('IP Address')
                        ->disabled(),

                    TextInput::make('user_agent')
                        ->label('User Agent')
                        ->disabled(),

                    TextInput::make('created_at')
                        ->label('Timestamp')
                        ->disabled(),
                ])
                ->columns(2)
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
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'point_approved' => 'success',
                        'point_rejected' => 'danger',
                        'user_banned' => 'danger',
                        'user_muted' => 'warning',
                        'user_unbanned' => 'success',
                        'user_promoted' => 'primary',
                        'user_demoted' => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),

                TextColumn::make('description')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-'),

                TextColumn::make('subject_id')
                    ->label('Subject ID'),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->options(fn (): array => ActivityLog::query()
                        ->distinct()
                        ->pluck('action', 'action')
                        ->toArray()),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
