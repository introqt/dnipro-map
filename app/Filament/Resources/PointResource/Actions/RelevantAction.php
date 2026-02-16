<?php

namespace App\Filament\Resources\PointResource\Actions;

use Filament\Actions\Action;
use Filament\Support\Colors\Color;
use Illuminate\Http\Request;

class RelevantAction
{
    public static function make(): Action
    {
        return Action::make('relevant')
            ->label('Relevant')
            ->color(Color::Green)
            ->url(fn (Request $request): string => $request->url().'?tableFilters[relevant]=true');
    }
}
