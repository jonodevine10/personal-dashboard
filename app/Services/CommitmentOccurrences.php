<?php

namespace App\Services;

use App\Models\Commitment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class CommitmentOccurrences
{
    /**
     * @return list<array{
     *     commitment: Commitment,
     *     starts_at: CarbonImmutable,
     *     ends_at: CarbonImmutable,
     *     starts_before_today: bool
     * }>
     */
    public function forDate(CarbonInterface|string $date): array
    {
        $clock = app(DashboardClock::class);
        $timezone = $clock->timezone();
        $dayStart = $date instanceof CarbonInterface
            ? CarbonImmutable::parse($date->toDateString(), $timezone)
            : CarbonImmutable::parse($date, $timezone)->startOfDay();
        $dayEnd = $dayStart->addDay();

        $commitments = Commitment::query()
            ->with('area')
            ->where('is_enabled', true)
            ->get();

        $occurrences = [];

        foreach ([$dayStart->subDay(), $dayStart] as $occurrenceDate) {
            foreach ($commitments as $commitment) {
                if (! in_array($occurrenceDate->isoWeekday(), $commitment->days_of_week, false)) {
                    continue;
                }

                $startsAt = CarbonImmutable::parse(
                    $occurrenceDate->toDateString().' '.$commitment->start_time,
                    $timezone,
                );
                $endDate = $commitment->end_time <= $commitment->start_time
                    ? $occurrenceDate->addDay()
                    : $occurrenceDate;
                $endsAt = CarbonImmutable::parse($endDate->toDateString().' '.$commitment->end_time, $timezone);
                $visibleStart = $startsAt->greaterThan($dayStart) ? $startsAt : $dayStart;
                $visibleEnd = $endsAt->lessThan($dayEnd) ? $endsAt : $dayEnd;

                if (! $visibleStart->lessThan($visibleEnd)) {
                    continue;
                }

                $occurrences[] = [
                    'commitment' => $commitment,
                    'starts_at' => $visibleStart,
                    'ends_at' => $visibleEnd,
                    'starts_before_today' => $startsAt->lessThan($dayStart),
                ];
            }
        }

        usort(
            $occurrences,
            fn (array $left, array $right): int => $left['starts_at']->getTimestamp() <=> $right['starts_at']->getTimestamp(),
        );

        return $occurrences;
    }
}
