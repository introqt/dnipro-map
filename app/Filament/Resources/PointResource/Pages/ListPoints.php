<?php

namespace App\Filament\Resources\PointResource\Pages;

use App\Enums\PointStatus;
use App\Filament\Resources\PointResource;
use App\Models\Point;
use App\Services\ActivityLogger;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ListPoints extends ListRecords
{
    protected static string $resource = PointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    BulkAction::make('approve')
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
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('reject')
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
                        ->deselectRecordsAfterCompletion(),

                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
