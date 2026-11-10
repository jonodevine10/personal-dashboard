<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\TodayCapacity;
use App\Filament\Widgets\TodayCommitments;
use App\Filament\Widgets\TodayTasks;
use Filament\Pages\Dashboard;

class Today extends Dashboard
{
    protected static ?string $title = 'Today';

    protected static ?string $navigationLabel = 'Today';

    protected static ?int $navigationSort = 0;

    public function getWidgets(): array
    {
        return [
            TodayTasks::class,
            TodayCapacity::class,
            TodayCommitments::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['md' => 2];
    }
}
