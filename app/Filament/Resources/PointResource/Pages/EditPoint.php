<?php

namespace App\Filament\Resources\PointResource\Pages;

use App\Enums\PointStatus;
use App\Filament\Resources\PointResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditPoint extends EditRecord
{
    protected static string $resource = PointResource::class;

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

                    $this->refreshFormData(['status', 'moderated_by', 'moderated_at', 'rejection_reason']);
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

                    $this->refreshFormData(['status', 'moderated_by', 'moderated_at']);
                }),

            DeleteAction::make(),
        ];
    }
}
