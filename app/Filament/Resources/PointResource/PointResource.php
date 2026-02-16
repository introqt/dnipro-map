<?php

namespace App\Filament\Resources\PointResource;

use App\Enums\PointStatus;
use App\Enums\PointType;
use App\Filament\Resources\PointResource\Pages\EditPoint;
use App\Filament\Resources\PointResource\Pages\ListPoints;
use App\Filament\Resources\PointResource\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\PointResource\RelationManagers\VotesRelationManager;
use App\Filament\Resources\PointResource\Widgets\MapPicker;
use App\Models\Point;
use Auth;
use EduardoRibeiroDev\FilamentLeaflet\Support\Markers\Marker;
use EduardoRibeiroDev\FilamentLeaflet\Tables\MapColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;

class PointResource extends Resource
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'webm'];

    private const MAX_MEDIA_FILES = 5;

    private const MAX_MEDIA_SIZE_KB = 51200;

    private const IMAGE_PREVIEW_LIMIT = 3;

    private const IMAGE_PREVIEW_HEIGHT = 100;

    private const NO_VIDEO_LABEL = 'x';

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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.telegram_id')
                    ->label('Author')
                    ->searchable(),

                TextColumn::make('description')
                    ->limit(255)
                    ->searchable(),

                ImageColumn::make('media')
                    ->label('Images')
                    ->state(fn (Point $record): array => self::filterMediaByExtensions(
                        $record->media ?? [],
                        self::IMAGE_EXTENSIONS
                    ))
                    ->stacked()
                    ->limit(self::IMAGE_PREVIEW_LIMIT)
                    ->imageHeight(self::IMAGE_PREVIEW_HEIGHT)
                    ->limitedRemainingText(),

                TextColumn::make('media')
                    ->label('Videos')
                    ->getStateUsing(fn (Point $record): int => count(
                        self::filterMediaByExtensions($record->media ?? [], self::VIDEO_EXTENSIONS)
                    ))
                    ->formatStateUsing(fn (int $state): string => $state === 0
                        ? self::NO_VIDEO_LABEL
                        : 'View (' . $state . ')'
                    )
                    ->url(fn (Point $record): ?string => self::firstVideoUrl($record->media ?? []))
                    ->openUrlInNewTab(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof PointType ? $state->label() : (string) $state)
                    ->color(fn ($state): string => $state instanceof PointType ? $state->color() : 'gray'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof PointStatus ? $state->label() : (string) $state)
                    ->color(fn ($state): string => $state instanceof PointStatus ? $state->color() : 'gray'),

                MapColumn::make('location')
                    ->height(100)
                    ->zoom(14)
                    ->pickMarker(fn(Marker $marker): Marker => $marker->icon(size: [14, 25]))
                    ->static(),

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
                    ))
                    ->default(PointStatus::Pending->value),

                SelectFilter::make('type')
                    ->options(collect(PointType::cases())->mapWithKeys(
                        fn (PointType $type): array => [$type->value => $type->label()]
                    )),

                Filter::make('relevant')
                    ->label('Relevant (last 3h)')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subHours(3))),
            ])
            ->recordActions([
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

    /** @param array<int, string> $media */
    private static function filterMediaByExtensions(array $media, array $extensions): array
    {
        return array_values(array_filter($media, function (string $path) use ($extensions): bool {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            return in_array($extension, $extensions, true);
        }));
    }

    /** @param array<int, string> $media */
    private static function firstVideoUrl(array $media): ?string
    {
        $videos = self::filterMediaByExtensions($media, self::VIDEO_EXTENSIONS);

        if ($videos === []) {
            return null;
        }

        return Storage::disk('public')->url($videos[0]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPoints::route('/'),
            'edit' => EditPoint::route('/{record}/edit'),
        ];
    }
}
