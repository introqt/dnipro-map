<?php

namespace App\Filament\Resources\ChannelMessageResource;

use App\Filament\Resources\ChannelMessageResource\Pages;
use App\Models\ChannelMessage;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ChannelMessageResource extends Resource
{
    protected static ?string $model = ChannelMessage::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Channel Messages';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Message')
                ->schema([
                    TextInput::make('channel_id')
                        ->label('Channel ID')
                        ->disabled(),

                    TextInput::make('message_id')
                        ->label('Message ID')
                        ->disabled(),

                    Textarea::make('raw_message')
                        ->label('Raw Message')
                        ->rows(5)
                        ->disabled(),

                    Textarea::make('parsed_text')
                        ->label('Parsed Text')
                        ->rows(3)
                        ->disabled(),
                ])
                ->columns(2),

            Section::make('Parsed Coordinates')
                ->schema([
                    TextInput::make('parsed_lat')
                        ->label('Latitude')
                        ->disabled(),

                    TextInput::make('parsed_lon')
                        ->label('Longitude')
                        ->disabled(),
                ])
                ->columns(2),

            Section::make('Metadata')
                ->schema([
                    KeyValue::make('keywords')
                        ->disabled(),

                    KeyValue::make('metadata')
                        ->disabled(),

                    DateTimePicker::make('processed_at')
                        ->disabled(),
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

                TextColumn::make('channel_id')
                    ->label('Channel')
                    ->searchable(),

                TextColumn::make('message_id')
                    ->label('Msg ID')
                    ->sortable(),

                TextColumn::make('parsed_text')
                    ->label('Text')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('parsed_lat')
                    ->label('Lat')
                    ->numeric(5)
                    ->toggleable(),

                TextColumn::make('parsed_lon')
                    ->label('Lon')
                    ->numeric(5)
                    ->toggleable(),

                IconColumn::make('processed_at')
                    ->label('Processed')
                    ->boolean()
                    ->getStateUsing(fn (ChannelMessage $record): bool => $record->processed_at !== null),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('processed')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('processed_at'),
                        false: fn ($query) => $query->whereNull('processed_at'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChannelMessages::route('/'),
            'view' => Pages\ViewChannelMessage::route('/{record}'),
        ];
    }
}
