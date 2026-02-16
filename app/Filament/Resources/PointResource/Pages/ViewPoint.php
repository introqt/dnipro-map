<?php

namespace App\Filament\Resources\PointResource\Pages;

use App\Enums\PointStatus;
use App\Filament\Resources\PointResource\PointResource;
use App\Services\ActivityLogger;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewPoint extends ViewRecord
{
    protected static string $resource = PointResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        $this->redirect(static::getResource()::getUrl('edit', ['record' => $record]), navigate: true);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === PointStatus::Pending)
                ->action(function (): void {
                    $this->record->update([
                        'status' => PointStatus::Active,
                        'moderated_by' => Auth::id(),
                        'moderated_at' => now(),
                        'rejection_reason' => null,
                    ]);

                    ActivityLogger::log('point_approved', $this->record, 'Point approved');
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === PointStatus::Pending)
                ->action(function (): void {
                    $this->record->update([
                        'status' => PointStatus::Rejected,
                        'moderated_by' => Auth::id(),
                        'moderated_at' => now(),
                    ]);

                    ActivityLogger::log('point_rejected', $this->record, 'Point rejected');
                }),

            EditAction::make(),
        ];
    }
}
