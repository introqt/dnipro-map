<?php

namespace App\Filament\Resources\PointResource\Actions;

use App\Enums\PointStatus;
use Filament\Actions\Action;
use Filament\Support\Colors\Color;
use Illuminate\Http\Request;

class PendingAction
{
    public static function make(): Action
    {
        return Action::make('pending')
            ->label('Pending')
            ->color(Color::Orange)
            ->url(fn (Request $request): string => $request->url().'?tableFilters[status]='.PointStatus::Pending->value);
    }
}
