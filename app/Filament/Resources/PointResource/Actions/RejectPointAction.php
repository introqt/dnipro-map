<?php

namespace App\Filament\Resources\PointResource\Actions;

use App\Enums\PointStatus;
use App\Services\ActivityLogger;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;

class RejectPointAction
{
    public static function make(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn ($record): bool => $record->status === PointStatus::Pending)
            ->action(function ($record, $livewire): void {
                $record->update([
                    'status' => PointStatus::Rejected,
                    'moderated_by' => Auth::id(),
                    'moderated_at' => now(),
                ]);

                ActivityLogger::log('point_rejected', $record, 'Point rejected');
                $livewire->refreshFormData(['status', 'moderated_by', 'moderated_at']);
            });
    }
}
