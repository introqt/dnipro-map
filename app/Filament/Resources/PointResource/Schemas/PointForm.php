<?php

namespace App\Filament\Resources\PointResource\Schemas;

use App\Enums\PointStatus;
use App\Enums\PointType;
use App\Filament\Resources\PointResource\Widgets\MapPicker;
use Auth;
use EduardoRibeiroDev\FilamentLeaflet\Support\Markers\Marker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PointForm
{
    private const MAX_MEDIA_FILES = 5;

    private const MAX_MEDIA_SIZE_KB = 51200;

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Location')
                ->schema([
                    MapPicker::make('location')
                        ->height(300)
                        ->default(null)
                        ->required()
                        ->columnSpanFull()
                        ->pickMarker(fn (Marker $marker): Marker => $marker->icon(size: [14, 22]))
                        ->afterStateUpdated(function (array $state, callable $set): void {
                            $set('latitude', $state['latitude'] ?? null);
                            $set('longitude', $state['longitude'] ?? null);
                        })
                        ->zoom(12),

                    Hidden::make('user_id')
                        ->label('Author User ID')
                        ->default(fn () => Auth::id())
                        ->required(),

                    Hidden::make('latitude')
                        ->required(),

                    Hidden::make('longitude')
                        ->required(),

                    Textarea::make('description')
                        ->required()
                        ->maxLength(1000)
                        ->rows(3)
                        ->columnSpanFull(),

                    Select::make('type')
                        ->options(collect(PointType::cases())->mapWithKeys(
                            fn (PointType $type): array => [$type->value => $type->label()]
                        ))
                        ->default(PointType::StaticDanger->value)
                        ->required(),

                    Select::make('status')
                        ->options(collect(PointStatus::cases())->mapWithKeys(
                            fn (PointStatus $status): array => [$status->value => $status->label()]
                        ))
                        ->default(fn (): string => Auth::user()?->isAdmin()
                            ? PointStatus::Active->value
                            : PointStatus::Pending->value
                        )
                        ->required(),
                ])
                ->columns(2),

            Section::make('Content')
                ->schema([
                    FileUpload::make('media')
                        ->maxSize(self::MAX_MEDIA_SIZE_KB)
                        ->label('Photos & Videos')
                        ->multiple()
                        ->maxFiles(self::MAX_MEDIA_FILES)
                        ->disk('public')
                        ->directory('points')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/*', 'video/*'])
                        ->panelLayout('grid')
                        ->openable(true)
                        ->fetchFileInformation(false)
                        ->columnSpanFull(),
                ]),

            Section::make('Moderation')
                ->schema([
                    TextInput::make('rejection_reason')
                        ->maxLength(255),
                ])
                ->collapsed()
                ->collapsible(),
        ]);
    }
}
