<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Users';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->schema([
                    TextInput::make('telegram_id')
                        ->label('Telegram ID')
                        ->required()
                        ->numeric()
                        ->disabled(),

                    TextInput::make('first_name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->email()
                        ->maxLength(255),
                ])
                ->columns(2),

            Section::make('Role & Status')
                ->schema([
                    Select::make('role')
                        ->options(collect(UserRole::cases())->mapWithKeys(
                            fn (UserRole $role): array => [$role->value => ucfirst($role->value)]
                        ))
                        ->required(),

                    Select::make('status')
                        ->options(collect(UserStatus::cases())->mapWithKeys(
                            fn (UserStatus $status): array => [$status->value => $status->label()]
                        ))
                        ->required(),

                    TextInput::make('ban_reason')
                        ->maxLength(255),
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

                TextColumn::make('telegram_id')
                    ->label('Telegram ID')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof UserRole ? ucfirst($state->value) : (string) $state)
                    ->color(fn ($state): string => $state instanceof UserRole ? match ($state) {
                        UserRole::Admin => 'primary',
                        UserRole::User => 'gray',
                    } : 'gray'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof UserStatus ? $state->label() : (string) $state)
                    ->color(fn ($state): string => $state instanceof UserStatus ? $state->color() : 'gray'),

                TextColumn::make('points_count')
                    ->counts('points')
                    ->label('Points')
                    ->sortable(),

                TextColumn::make('comments_count')
                    ->counts('comments')
                    ->label('Comments')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role')
                    ->options(collect(UserRole::cases())->mapWithKeys(
                        fn (UserRole $role): array => [$role->value => ucfirst($role->value)]
                    )),

                SelectFilter::make('status')
                    ->options(collect(UserStatus::cases())->mapWithKeys(
                        fn (UserStatus $status): array => [$status->value => $status->label()]
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
        ];
    }
}
