<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommentResource\Pages;
use App\Models\Comment;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('point_id')
                ->relationship('point', 'description')
                ->required()
                ->searchable()
                ->preload(),

            Select::make('user_id')
                ->relationship('user', 'first_name')
                ->required()
                ->searchable()
                ->preload(),

            Textarea::make('text')
                ->required()
                ->maxLength(1000)
                ->rows(3),
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
                    ->searchable()
                    ->sortable(),

                TextColumn::make('point.description')
                    ->label('Point')
                    ->limit(30)
                    ->searchable(),

                TextColumn::make('text')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
            'index' => Pages\ListComments::route('/'),
            'edit' => Pages\EditComment::route('/{record}/edit'),
        ];
    }
}
