<?php

namespace App\Filament\Resources\PointResource\Tables;

use App\Enums\PointStatus;
use App\Enums\PointType;
use App\Models\Point;
use EduardoRibeiroDev\FilamentLeaflet\Support\Markers\Marker;
use EduardoRibeiroDev\FilamentLeaflet\Tables\MapColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class PointTable
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'webm'];

    private const IMAGE_PREVIEW_LIMIT = 3;

    private const IMAGE_PREVIEW_HEIGHT = 100;

    private const NO_VIDEO_LABEL = 'x';

    public static function configure(Table $table): Table
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
                        : 'View ('.$state.')'
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
                    ->pickMarker(fn (Marker $marker): Marker => $marker->icon(size: [14, 25]))
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
}
