<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class DashboardClock
{
    public function timezone(): string
    {
        return (string) config('dashboard.timezone');
    }

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone())->startOfDay();
    }
}
