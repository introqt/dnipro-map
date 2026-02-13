<?php

namespace App\Filament\Widgets;

use App\Models\Point;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class PointsChart extends ChartWidget
{
    protected ?string $heading = 'Points Created (Last 30 Days)';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn (int $daysAgo): Carbon => now()->subDays($daysAgo)->startOfDay());

        $pointsByDay = Point::query()
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $data = $days->map(fn (Carbon $day): int => $pointsByDay[$day->toDateString()] ?? 0)->toArray();
        $labels = $days->map(fn (Carbon $day): string => $day->format('M d'))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Points',
                    'data' => $data,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
