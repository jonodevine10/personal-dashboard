<?php

namespace Tests\Feature;

use App\Actions\CreateProject;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Filament\Pages\Today;
use App\Filament\Widgets\TodayCapacity;
use App\Filament\Widgets\TodayCommitments;
use App\Filament\Widgets\TodayTasks;
use App\Models\Area;
use App\Models\DailyCapacity;
use App\Models\DashboardSetting;
use App\Models\InboxItem;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TodayPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dashboard.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-11-10 09:00:00', 'UTC'));
        $this->seed();
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_today_is_the_authenticated_default_landing_page(): void
    {
        $this->get('/admin')->assertOk()->assertSee('Today’s tasks');
        $this->assertSame('/admin', Today::getUrl(isAbsolute: false));
    }

    public function test_today_tasks_are_pending_today_only_and_grouped_by_priority(): void
    {
        $must = Task::create([
            'title' => 'Must do', 'tier' => 'must', 'scheduled_for' => '2026-11-10',
        ]);
        $should = Task::create([
            'title' => 'Should do', 'tier' => 'should', 'scheduled_for' => '2026-11-10',
        ]);
        $could = Task::create([
            'title' => 'Could do', 'tier' => 'could', 'scheduled_for' => '2026-11-10',
        ]);
        $tomorrow = Task::create([
            'title' => 'Tomorrow', 'scheduled_for' => '2026-11-11',
        ]);
        $unscheduled = Task::create(['title' => 'Unscheduled']);
        $done = Task::create([
            'title' => 'Done today', 'scheduled_for' => '2026-11-10', 'status' => TaskStatus::Done,
        ]);

        Livewire::test(TodayTasks::class)
            ->assertCanSeeTableRecords([$must, $should, $could])
            ->assertCanNotSeeTableRecords([$tomorrow, $unscheduled, $done])
            ->assertSee('Must')
            ->assertSee('Should')
            ->assertSee('Could');
    }

    public function test_today_task_create_action_schedules_the_new_task_for_today(): void
    {
        Livewire::test(TodayTasks::class)
            ->assertTableHeaderActionsExistInOrder(['create'])
            ->callTableAction('create', null, [
                'title' => 'Write abstract',
                'tier' => 'must',
                'scheduled_for' => '2026-11-10',
                'estimate_minutes' => 60,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Write abstract',
            'tier' => 'must',
            'scheduled_for' => '2026-11-10',
            'status' => 'pending',
        ]);
    }

    public function test_non_project_task_can_be_completed_from_today(): void
    {
        $task = Task::create(['title' => 'Send email', 'scheduled_for' => '2026-11-10']);

        Livewire::test(TodayTasks::class)
            ->callTableAction('complete', $task)
            ->assertHasNoTableActionErrors();

        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_completing_an_active_next_action_from_today_creates_a_replacement(): void
    {
        $project = app(CreateProject::class)->handle([
            'area_id' => Area::where('name', 'Dissertation')->value('id'),
            'title' => 'Dissertation',
            'status' => ProjectStatus::Active,
        ], ['title' => 'Draft abstract', 'scheduled_for' => '2026-11-10']);
        $nextAction = $project->nextActionTask;

        Livewire::test(TodayTasks::class)
            ->callTableAction('complete', $nextAction, ['replacement_title' => 'Draft introduction'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(TaskStatus::Done, $nextAction->fresh()->status);
        $this->assertSame('Draft introduction', $project->fresh()->nextActionTask->title);
    }

    public function test_completing_an_active_next_action_requires_a_replacement_or_disposition(): void
    {
        $project = app(CreateProject::class)->handle([
            'area_id' => Area::where('name', 'Dissertation')->value('id'),
            'title' => 'Dissertation',
            'status' => ProjectStatus::Active,
        ], ['title' => 'Draft abstract', 'scheduled_for' => '2026-11-10']);
        $nextAction = $project->nextActionTask;

        Livewire::test(TodayTasks::class)
            ->callTableAction('complete', $nextAction)
            ->assertHasTableActionErrors(['replacement_title']);

        $this->assertSame(TaskStatus::Pending, $nextAction->fresh()->status);
        $this->assertSame($nextAction->id, $project->fresh()->next_action_task_id);
    }

    public function test_today_reschedule_uses_the_carry_counter_action(): void
    {
        $task = Task::create([
            'title' => 'Move me', 'scheduled_for' => '2026-11-10',
        ]);

        Livewire::test(TodayTasks::class)
            ->callTableAction('reschedule', $task, ['scheduled_for' => '2026-11-11'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('2026-11-11', $task->fresh()->scheduled_for->toDateString());
        $this->assertSame(1, $task->fresh()->carry_count);
    }

    public function test_capacity_widget_shows_and_saves_default_zero_and_daily_override(): void
    {
        Livewire::test(TodayCapacity::class)
            ->assertSee('4h 0m focus time')
            ->set('defaultFocusMinutes', '0')
            ->call('saveDefaultFocus')
            ->assertHasNoErrors()
            ->set('dailyFocusMinutes', '0')
            ->call('saveDailyFocus')
            ->assertHasNoErrors();

        $this->assertSame(0, DashboardSetting::findOrFail(1)->default_focus_minutes);
        $this->assertDatabaseHas('daily_capacities', [
            'date' => '2026-11-10',
            'focus_minutes' => 0,
        ]);
    }

    public function test_capacity_widget_displays_known_load_and_flags_missing_estimates(): void
    {
        Task::create([
            'title' => 'Known load', 'scheduled_for' => '2026-11-10', 'estimate_minutes' => 260,
        ]);
        Task::create(['title' => 'Unknown load', 'scheduled_for' => '2026-11-10']);

        Livewire::test(TodayCapacity::class)
            ->assertSee('4h 20m estimated')
            ->assertSee('20m over capacity')
            ->assertSee('1 scheduled')
            ->assertSee('task')
            ->assertSee('needs an estimate');
    }

    public function test_capacity_widget_can_reset_to_default_and_reject_negative_minutes(): void
    {
        DailyCapacity::create(['date' => '2026-11-10', 'focus_minutes' => 90]);
        $widget = Livewire::test(TodayCapacity::class)
            ->set('dailyFocusMinutes', '-1')
            ->call('saveDailyFocus')
            ->assertHasErrors(['dailyFocusMinutes'])
            ->call('resetDailyFocus')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('daily_capacities', ['date' => '2026-11-10']);
        $this->assertSame('240', $widget->get('dailyFocusMinutes'));
    }

    public function test_inbox_count_is_shown_on_today_and_reacts_to_capture_events(): void
    {
        InboxItem::create(['text' => 'First']);
        $widget = Livewire::test(TodayCommitments::class)
            ->assertSee('Inbox')
            ->assertSee('1');

        InboxItem::create(['text' => 'Second']);
        $widget->dispatch('dashboard-data-changed')->assertSee('2');
    }
}
