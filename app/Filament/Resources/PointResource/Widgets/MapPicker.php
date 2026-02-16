<?php

namespace App\Filament\Resources\PointResource\Widgets;

use App\Models\Point;
use EduardoRibeiroDev\FilamentLeaflet\Fields\MapPicker as MapWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class MapPicker extends MapWidget
{
    use InteractsWithPageTable;

    protected string $view = 'filament.resources.point-resource.widgets.map-picker';

    public ?Point $record = null;

    protected ?string $markerResource = PointResource::class;

    protected array $mapCenter = [48.4647, 35.0462];

    protected int $defaultZoom = 12;
}
