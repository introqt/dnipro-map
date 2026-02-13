<?php

namespace App\Enums;

enum PointType: string
{
    case StaticDanger = 'static_danger';
    case DynamicDanger = 'dynamic_danger';
    case Infrastructure = 'infrastructure';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::StaticDanger => 'Static Danger',
            self::DynamicDanger => 'Dynamic Danger',
            self::Infrastructure => 'Infrastructure',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::StaticDanger => 'danger',
            self::DynamicDanger => 'warning',
            self::Infrastructure => 'info',
            self::Other => 'gray',
        };
    }
}
