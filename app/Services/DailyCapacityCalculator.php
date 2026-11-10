<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\DailyCapacity;
use App\Models\DashboardSetting;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class DailyCapacityCalculator
{
    /**
     * @return array{
     *     date: string,
     *     focus_minutes: int,
     *     estimated_minutes: int,
     *     missing_estimate_count: int,
     *     remaining_minutes: int,
     *     is_over_capacity: bool
     * }
     */
    public function forDate(CarbonInterface|string $date): array
    {
        $localDate = $date instanceof CarbonInterface
            ? $date->copy()->setTimezone(app(DashboardClock::class)->timezone())->toDateString()
            : CarbonImmutable::parse($date, app(DashboardClock::class)->timezone())->toDateString();

        $tasks = Task::query()
            ->whereDate('scheduled_for', $localDate)
            ->where('status', TaskStatus::Pending);

        $estimatedMinutes = (int) (clone $tasks)->whereNotNull('estimate_minutes')->sum('estimate_minutes');
        $missingEstimateCount = (clone $tasks)->whereNull('estimate_minutes')->count();
        $focusMinutes = DailyCapacity::query()->whereDate('date', $localDate)->value('focus_minutes')
            ?? DashboardSetting::query()->findOrFail(1)->default_focus_minutes;

        return [
            'date' => $localDate,
            'focus_minutes' => (int) $focusMinutes,
            'estimated_minutes' => $estimatedMinutes,
            'missing_estimate_count' => $missingEstimateCount,
            'remaining_minutes' => max(0, (int) $focusMinutes - $estimatedMinutes),
            'is_over_capacity' => $estimatedMinutes > $focusMinutes,
        ];
    }
}
