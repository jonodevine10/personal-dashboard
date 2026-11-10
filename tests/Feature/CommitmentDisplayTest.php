<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Commitment;
use App\Services\CommitmentOccurrences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitmentDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dashboard.timezone' => 'Europe/London']);
        $this->seed();
    }

    public function test_it_includes_the_overlap_from_a_commitment_that_started_the_previous_day(): void
    {
        Commitment::create([
            'area_id' => Area::where('name', 'M&S')->value('id'),
            'title' => 'Overnight shift',
            'days_of_week' => [1],
            'start_time' => '22:00',
            'end_time' => '06:00',
            'is_enabled' => true,
        ]);

        $occurrences = app(CommitmentOccurrences::class)->forDate('2026-11-10');

        $this->assertCount(1, $occurrences);
        $this->assertSame('Overnight shift', $occurrences[0]['commitment']->title);
        $this->assertSame('00:00', $occurrences[0]['starts_at']->format('H:i'));
        $this->assertSame('06:00', $occurrences[0]['ends_at']->format('H:i'));
        $this->assertTrue($occurrences[0]['starts_before_today']);
        $this->assertSame('Europe/London', $occurrences[0]['starts_at']->timezoneName);
    }

    public function test_it_includes_commitments_starting_today_and_excludes_disabled_or_non_overlapping_days(): void
    {
        $areaId = Area::where('name', 'TheGallery Work')->value('id');
        Commitment::create([
            'area_id' => $areaId,
            'title' => 'Tuesday meeting',
            'days_of_week' => [2],
            'start_time' => '09:30',
            'end_time' => '10:30',
            'is_enabled' => true,
        ]);
        Commitment::create([
            'area_id' => $areaId,
            'title' => 'Disabled meeting',
            'days_of_week' => [2],
            'start_time' => '11:00',
            'end_time' => '12:00',
            'is_enabled' => false,
        ]);
        Commitment::create([
            'area_id' => $areaId,
            'title' => 'Monday ends before Tuesday',
            'days_of_week' => [1],
            'start_time' => '08:00',
            'end_time' => '09:00',
            'is_enabled' => true,
        ]);

        $occurrences = app(CommitmentOccurrences::class)->forDate('2026-11-10');

        $this->assertCount(1, $occurrences);
        $this->assertSame('Tuesday meeting', $occurrences[0]['commitment']->title);
        $this->assertSame('09:30', $occurrences[0]['starts_at']->format('H:i'));
        $this->assertSame('10:30', $occurrences[0]['ends_at']->format('H:i'));
        $this->assertFalse($occurrences[0]['starts_before_today']);
    }

    public function test_overnight_commitment_on_the_selected_day_is_clipped_to_day_end(): void
    {
        Commitment::create([
            'area_id' => Area::where('name', 'M&S')->value('id'),
            'title' => 'Night shift',
            'days_of_week' => [2],
            'start_time' => '22:00',
            'end_time' => '06:00',
            'is_enabled' => true,
        ]);

        $occurrences = app(CommitmentOccurrences::class)->forDate('2026-11-10');

        $this->assertCount(1, $occurrences);
        $this->assertSame('22:00', $occurrences[0]['starts_at']->format('H:i'));
        $this->assertSame('00:00', $occurrences[0]['ends_at']->format('H:i'));
    }
}
