<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaResource\Pages;
use App\Models\Media;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Media Gallery';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('File Info')
                ->schema([
                    TextInput::make('file_name')
                        ->disabled(),

                    TextInput::make('file_path')
                        ->disabled(),

                    TextInput::make('mime_type')
                        ->disabled(),

                    TextInput::make('file_size')
                        ->label('Size (bytes)')
                        ->disabled(),

                    TextInput::make('collection')
                        ->disabled(),
                ])
                ->columns(2),

            Section::make('Associations')
                ->schema([
                    TextInput::make('mediable_type')
                        ->label('Attached To (Type)')
                        ->disabled(),

                    TextInput::make('mediable_id')
                        ->label('Attached To (ID)')
                        ->disabled(),

                    TextInput::make('uploader.first_name')
                        ->label('Uploaded By')
                        ->disabled(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                ImageColumn::make('file_path')
                    ->label('Preview')
                    ->disk('public')
                    ->square()
                    ->size(40),

                TextColumn::make('file_name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('mime_type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? number_format($state / 1024, 1).' KB' : '-')
                    ->sortable(),

                TextColumn::make('collection')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('uploader.first_name')
                    ->label('Uploaded By')
                    ->sortable(),

                TextColumn::make('mediable_type')
                    ->label('Attached To')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('collection')
                    ->options(fn (): array => Media::query()
                        ->distinct()
                        ->whereNotNull('collection')
                        ->pluck('collection', 'collection')
                        ->toArray()),
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
            'index' => Pages\ListMedia::route('/'),
            'view' => Pages\ViewMedia::route('/{record}'),
        ];
    }
}
