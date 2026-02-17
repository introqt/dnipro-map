<?php

namespace App\Filament\Resources\PointResource\Actions;

use App\Enums\PointStatus;
use App\Models\Point;
use App\Services\ActivityLogger;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class RejectBulkAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('reject')
            ->label('Reject Selected')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (Collection $records): void {
                $records->each(function (Point $point): void {
                    $point->update([
                        'status' => PointStatus::Rejected,
                        'moderated_by' => Auth::id(),
                        'moderated_at' => now(),
                    ]);

                    ActivityLogger::log('point_rejected', $point, 'Point rejected (bulk)');
                });
            })
            ->deselectRecordsAfterCompletion();
    }
}
