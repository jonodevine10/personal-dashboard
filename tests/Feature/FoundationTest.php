<?php

namespace Tests\Feature;

use App\Actions\CreateProject;
use App\Models\Area;
use App\Models\Commitment;
use App\Models\DailyCapacity;
use App\Models\DailyReview;
use App\Models\DashboardSetting;
use App\Models\FixedDate;
use App\Models\InboxItem;
use App\Models\User;
use App\Models\WaitingOn;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_is_repeatable_and_does_not_overwrite_edits_or_create_credentials(): void
    {
        $this->seed();
        $this->assertDatabaseCount('areas', 7);
        $this->assertDatabaseCount('commitments', 2);
        $this->assertDatabaseCount('users', 0);
        $this->assertSame(240, DashboardSetting::findOrFail(1)->default_focus_minutes);

        $area = Area::where('name', 'Training')->firstOrFail();
        $area->update(['colour' => '#FFFFFF', 'sort_order' => 99]);
        $commitment = Commitment::firstOrFail();
        $this->assertFalse($commitment->is_enabled);
        $this->assertSame([], $commitment->days_of_week);
        $commitment->update([
            'title' => 'My shifts',
            'days_of_week' => [1, 2, 3, 4],
            'is_enabled' => true,
        ]);
        DashboardSetting::findOrFail(1)->update(['default_focus_minutes' => 120]);

        $this->seed();
        $this->assertDatabaseCount('areas', 7);
        $this->assertDatabaseCount('commitments', 2);
        $this->assertSame('#FFFFFF', $area->fresh()->colour);
        $this->assertSame(99, $area->fresh()->sort_order);
        $this->assertSame('My shifts', $commitment->fresh()->title);
        $this->assertTrue($commitment->fresh()->is_enabled);
        $this->assertSame(120, DashboardSetting::findOrFail(1)->default_focus_minutes);
    }

    public function test_domain_records_round_trip_and_calendar_dates_are_stored_without_times(): void
    {
        $this->seed();
        $capacity = DailyCapacity::create(['date' => '2026-11-08', 'focus_minutes' => 0]);
        $fixedDate = FixedDate::create([
            'area_id' => Area::firstOrFail()->id, 'title' => 'Deadline', 'date' => '2026-11-20',
        ]);
        $waiting = WaitingOn::create([
            'description' => 'Awaiting feedback', 'who' => 'Reviewer', 'since_date' => '2026-11-01',
            'chase_on' => '2026-11-09',
        ]);
        $inbox = InboxItem::create(['text' => 'Remember to book a slot']);
        $review = DailyReview::create([
            'date' => '2026-11-07', 'wins' => 'Finished a task',
            'carried_over' => [['task_id' => 1, 'title' => 'Read', 'scheduled_for' => '2026-11-08']],
            'notes' => 'Ready for tomorrow',
        ]);

        $this->assertSame('2026-11-08', $capacity->fresh()->getRawOriginal('date'));
        $this->assertSame(0, $capacity->fresh()->focus_minutes);
        $this->assertSame('2026-11-20', $fixedDate->fresh()->date->toDateString());
        $this->assertSame('2026-11-09', $waiting->fresh()->chase_on->toDateString());
        $this->assertNull($inbox->fresh()->triaged_at);
        $this->assertSame('2026-11-08', $review->fresh()->carried_over[0]['scheduled_for']);
    }

    public function test_invalid_capacity_is_reported_as_validation_error(): void
    {
        $this->expectException(ValidationException::class);
        DailyCapacity::create(['date' => '2026-11-08', 'focus_minutes' => -1]);
    }

    public function test_database_rejects_negative_capacity_even_without_model_events(): void
    {
        $this->expectException(QueryException::class);
        DB::table('daily_capacities')->insert(['date' => '2026-11-08', 'focus_minutes' => -1]);
    }

    public function test_enabled_commitments_require_unique_valid_weekdays(): void
    {
        $this->seed();
        $this->expectException(ValidationException::class);
        Commitment::firstOrFail()->update(['days_of_week' => [1, 1, 8], 'is_enabled' => true]);
    }

    public function test_enabled_commitments_cannot_have_empty_weekdays(): void
    {
        $this->seed();
        $this->expectException(ValidationException::class);
        Commitment::firstOrFail()->update(['is_enabled' => true]);
    }

    public function test_overnight_commitments_are_supported(): void
    {
        $this->seed();
        $commitment = Commitment::firstOrFail();
        $commitment->update([
            'days_of_week' => [1, 3], 'start_time' => '22:00', 'end_time' => '06:00', 'is_enabled' => true,
        ]);
        $this->assertSame('06:00', $commitment->fresh()->end_time);
    }

    public function test_only_one_account_can_be_inserted(): void
    {
        User::factory()->create();
        $this->expectException(QueryException::class);
        User::factory()->create();
    }

    public function test_the_first_account_must_have_the_owner_id(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['id' => 2]);
    }

    public function test_owner_id_cannot_be_changed(): void
    {
        $user = User::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $user->id)->update(['id' => 2]);
    }

    public function test_duplicate_daily_capacity_is_reported_as_validation_error(): void
    {
        DailyCapacity::create(['date' => '2026-11-08', 'focus_minutes' => 60]);
        $this->expectException(ValidationException::class);
        DailyCapacity::create(['date' => '2026-11-08', 'focus_minutes' => 120]);
    }

    public function test_calendar_dates_reject_datetime_strings_instead_of_storing_mixed_formats(): void
    {
        $this->expectException(ValidationException::class);
        DailyCapacity::create(['date' => '2026-11-08 09:00:00', 'focus_minutes' => 60]);
    }

    public function test_migration_rolls_back_with_populated_circular_relationships(): void
    {
        $this->seed();
        app(CreateProject::class)->handle([
            'area_id' => Area::firstOrFail()->id, 'title' => 'Project', 'status' => 'active',
        ], ['title' => 'Next task']);

        $migration = require database_path('migrations/2026_11_07_000000_create_dashboard_tables.php');
        $migration->down();
        $migration->up();
        $this->seed();

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('areas', 7);
    }
}
