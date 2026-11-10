<?php

namespace Tests\Unit;

use App\Enums\TaskStatus;
use App\Models\DailyCapacity;
use App\Models\DashboardSetting;
use App\Models\Task;
use App\Services\DailyCapacityCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyCapacityCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_it_sums_pending_scheduled_estimates_and_reports_missing_estimates(): void
    {
        Task::create(['title' => 'Must', 'tier' => 'must', 'scheduled_for' => '2026-11-10', 'estimate_minutes' => 80]);
        Task::create(['title' => 'Should', 'scheduled_for' => '2026-11-10', 'estimate_minutes' => 55]);
        Task::create(['title' => 'No estimate', 'scheduled_for' => '2026-11-10']);
        Task::create(['title' => 'Unscheduled', 'estimate_minutes' => 90]);
        Task::create([
            'title' => 'Done', 'scheduled_for' => '2026-11-10', 'estimate_minutes' => 120,
            'status' => TaskStatus::Done,
        ]);
        Task::create(['title' => 'Tomorrow', 'scheduled_for' => '2026-11-11', 'estimate_minutes' => 120]);

        $result = app(DailyCapacityCalculator::class)->forDate('2026-11-10');

        $this->assertSame('2026-11-10', $result['date']);
        $this->assertSame(240, $result['focus_minutes']);
        $this->assertSame(135, $result['estimated_minutes']);
        $this->assertSame(1, $result['missing_estimate_count']);
        $this->assertSame(105, $result['remaining_minutes']);
        $this->assertFalse($result['is_over_capacity']);
    }

    public function test_it_flags_only_known_estimates_over_capacity(): void
    {
        DailyCapacity::create(['date' => '2026-11-10', 'focus_minutes' => 100]);
        Task::create(['title' => 'Known estimate', 'scheduled_for' => '2026-11-10', 'estimate_minutes' => 110]);
        Task::create(['title' => 'Unknown estimate', 'scheduled_for' => '2026-11-10']);

        $result = app(DailyCapacityCalculator::class)->forDate('2026-11-10');

        $this->assertSame(110, $result['estimated_minutes']);
        $this->assertSame(1, $result['missing_estimate_count']);
        $this->assertSame(0, $result['remaining_minutes']);
        $this->assertTrue($result['is_over_capacity']);
    }

    public function test_zero_focus_is_supported_and_exact_capacity_is_not_over_capacity(): void
    {
        DashboardSetting::findOrFail(1)->update(['default_focus_minutes' => 0]);
        $this->assertFalse(app(DailyCapacityCalculator::class)->forDate('2026-11-10')['is_over_capacity']);

        Task::create(['title' => 'One minute', 'scheduled_for' => '2026-11-10', 'estimate_minutes' => 1]);
        $result = app(DailyCapacityCalculator::class)->forDate('2026-11-10');

        $this->assertTrue($result['is_over_capacity']);
        $this->assertSame(1, $result['estimated_minutes']);
    }

    public function test_a_daily_override_takes_precedence_without_changing_the_default(): void
    {
        DashboardSetting::findOrFail(1)->update(['default_focus_minutes' => 240]);
        DailyCapacity::create(['date' => '2026-11-10', 'focus_minutes' => 0]);

        $this->assertSame(0, app(DailyCapacityCalculator::class)->forDate('2026-11-10')['focus_minutes']);
        $this->assertSame(240, app(DailyCapacityCalculator::class)->forDate('2026-11-11')['focus_minutes']);
        $this->assertSame(240, DashboardSetting::findOrFail(1)->default_focus_minutes);
    }

    public function test_carbon_dates_are_converted_to_the_configured_dashboard_timezone(): void
    {
        config(['dashboard.timezone' => 'Europe/London']);
        $date = CarbonImmutable::parse('2026-11-10 00:30:00', 'UTC');
        DailyCapacity::create(['date' => '2026-11-10', 'focus_minutes' => 0]);

        $this->assertSame('2026-11-10', app(DailyCapacityCalculator::class)->forDate($date)['date']);
        $this->assertSame(0, app(DailyCapacityCalculator::class)->forDate($date)['focus_minutes']);
    }
}
