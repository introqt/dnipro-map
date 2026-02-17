<?php

namespace App\Filament\Resources\PointResource\Actions;

use App\Enums\PointStatus;
use App\Services\ActivityLogger;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;

class ApprovePointAction
{
    public static function make(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn ($record): bool => $record->status === PointStatus::Pending)
            ->action(function ($record, $livewire): void {
                $record->update([
                    'status' => PointStatus::Active,
                    'moderated_by' => Auth::id(),
                    'moderated_at' => now(),
                    'rejection_reason' => null,
                ]);

                ActivityLogger::log('point_approved', $record, 'Point approved');
                $livewire->refreshFormData(['status', 'moderated_by', 'moderated_at', 'rejection_reason']);
            });
    }
}
