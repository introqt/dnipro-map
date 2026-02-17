<?php

namespace App\Filament\Resources\PointResource\Actions;

use App\Enums\PointStatus;
use App\Models\Point;
use App\Services\ActivityLogger;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ApproveBulkAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('approve')
            ->label('Approve Selected')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->action(function (Collection $records): void {
                $records->each(function (Point $point): void {
                    $point->update([
                        'status' => PointStatus::Active,
                        'moderated_by' => Auth::id(),
                        'moderated_at' => now(),
                        'rejection_reason' => null,
                    ]);

                    ActivityLogger::log('point_approved', $point, 'Point approved (bulk)');
                });
            })
            ->deselectRecordsAfterCompletion();
    }
}
