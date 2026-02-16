<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\UserResource\UserResource;
use App\Services\ActivityLogger;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ban')
                ->label('Ban')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Ban User')
                ->modalDescription('This will fully block the user from all API requests.')
                ->form([
                    Textarea::make('ban_reason')
                        ->label('Reason')
                        ->required()
                        ->maxLength(255),
                ])
                ->visible(fn (): bool => ! $this->record->isBanned() && ! $this->record->isAdmin())
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => UserStatus::Banned,
                        'banned_at' => now(),
                        'ban_reason' => $data['ban_reason'],
                    ]);

                    ActivityLogger::log('user_banned', $this->record, 'User banned', [
                        'reason' => $data['ban_reason'],
                    ]);
                }),

            Action::make('mute')
                ->label('Mute')
                ->icon('heroicon-o-speaker-x-mark')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Mute User')
                ->modalDescription('This will prevent the user from creating points, voting, and commenting.')
                ->form([
                    Textarea::make('ban_reason')
                        ->label('Reason')
                        ->required()
                        ->maxLength(255),
                ])
                ->visible(fn (): bool => $this->record->isActive() && ! $this->record->isAdmin())
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => UserStatus::Muted,
                        'banned_at' => now(),
                        'ban_reason' => $data['ban_reason'],
                    ]);

                    ActivityLogger::log('user_muted', $this->record, 'User muted', [
                        'reason' => $data['ban_reason'],
                    ]);
                }),

            Action::make('unban')
                ->label('Unban / Unmute')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->isBanned() || $this->record->isMuted())
                ->action(function (): void {
                    $previousStatus = $this->record->status;

                    $this->record->update([
                        'status' => UserStatus::Active,
                        'banned_at' => null,
                        'ban_reason' => null,
                    ]);

                    ActivityLogger::log('user_unbanned', $this->record, 'User unbanned/unmuted', [
                        'previous_status' => $previousStatus->value,
                    ]);
                }),

            Action::make('promote')
                ->label('Promote to Admin')
                ->icon('heroicon-o-shield-check')
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('This will give the user full admin access to the panel.')
                ->visible(fn (): bool => $this->record->role === UserRole::User)
                ->action(function (): void {
                    $this->record->update([
                        'role' => UserRole::Admin,
                    ]);

                    ActivityLogger::log('user_promoted', $this->record, 'User promoted to admin');
                }),

            Action::make('demote')
                ->label('Demote to User')
                ->icon('heroicon-o-arrow-down-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->isAdmin())
                ->action(function (): void {
                    $this->record->update([
                        'role' => UserRole::User,
                    ]);

                    ActivityLogger::log('user_demoted', $this->record, 'User demoted to regular user');
                }),
        ];
    }
}
