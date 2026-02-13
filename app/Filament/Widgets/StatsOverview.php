<?php

namespace App\Filament\Widgets;

use App\Enums\PointStatus;
use App\Enums\UserStatus;
use App\Models\Comment;
use App\Models\Point;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $totalPoints = Point::count();
        $pendingPoints = Point::where('status', PointStatus::Pending)->count();
        $activePoints = Point::where('status', PointStatus::Active)->count();
        $totalUsers = User::count();
        $bannedUsers = User::where('status', UserStatus::Banned)->count();
        $totalComments = Comment::count();

        return [
            Stat::make('Total Points', $totalPoints)
                ->description("{$activePoints} active, {$pendingPoints} pending")
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('primary'),

            Stat::make('Pending Moderation', $pendingPoints)
                ->description('Points awaiting review')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingPoints > 0 ? 'warning' : 'success'),

            Stat::make('Total Users', $totalUsers)
                ->description("{$bannedUsers} banned")
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Comments', $totalComments)
                ->description('Total comments')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('gray'),
        ];
    }
}
