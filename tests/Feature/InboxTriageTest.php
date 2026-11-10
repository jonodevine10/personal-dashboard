<?php

namespace Tests\Feature;

use App\Actions\CreateProject;
use App\Actions\TriageInboxItem;
use App\Enums\InboxTriageType;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskTier;
use App\Models\Area;
use App\Models\InboxItem;
use App\Models\Project;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InboxTriageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dashboard.timezone' => 'Europe/London']);
        $this->seed();
    }

    public function test_triaging_to_a_task_is_transactional_and_defaults_to_should_pending_unscheduled(): void
    {
        $item = InboxItem::create(['text' => 'Read the paper']);
        $task = app(TriageInboxItem::class)->handle($item, InboxTriageType::Task, [
            'title' => 'Read the paper',
        ]);

        $this->assertInstanceOf(Task::class, $task);
        $this->assertSame(TaskStatus::Pending, $task->status);
        $this->assertSame(TaskTier::Should, $task->tier);
        $this->assertNull($task->scheduled_for);
        $this->assertNull($task->project_id);
        $this->assertNotNull($item->fresh()->triaged_at);
    }

    public function test_task_triage_persists_optional_project_tier_date_and_estimate(): void
    {
        $project = $this->createProject('Project');
        $item = InboxItem::create(['text' => 'Prepare outline']);

        $task = app(TriageInboxItem::class)->handle($item, InboxTriageType::Task, [
            'title' => 'Prepare outline',
            'project_id' => $project->id,
            'tier' => TaskTier::Must->value,
            'scheduled_for' => '2026-11-11',
            'estimate_minutes' => 45,
        ]);

        $this->assertSame($project->id, $task->project_id);
        $this->assertSame(TaskTier::Must, $task->tier);
        $this->assertSame('2026-11-11', $task->scheduled_for->toDateString());
        $this->assertSame(45, $task->estimate_minutes);
        $this->assertSame($project->next_action_task_id, $project->fresh()->next_action_task_id);
        $this->assertNotSame($task->id, $project->fresh()->next_action_task_id);
    }

    public function test_project_note_is_appended_without_replacing_existing_notes_and_uses_local_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 22:30:00', 'UTC'));
        $project = $this->createProject('Project', 'Existing notes');
        $item = InboxItem::create(['text' => 'Ask Sam about the draft']);

        app(TriageInboxItem::class)->handle($item, InboxTriageType::ProjectNote, [
            'project_id' => $project->id,
        ]);

        $notes = $project->fresh()->notes;
        $this->assertStringStartsWith('Existing notes', $notes);
        $this->assertStringContainsString('**Inbox note — Tue 10 Nov 2026, 22:30**', $notes);
        $this->assertStringContainsString('Ask Sam about the draft', $notes);
        $this->assertNotNull($item->fresh()->triaged_at);

        Carbon::setTestNow();
    }

    public function test_parking_lot_triage_creates_a_paused_project_with_the_full_capture_in_notes(): void
    {
        $text = str_repeat('A long thought. ', 30);
        $item = InboxItem::create(['text' => $text]);

        $project = app(TriageInboxItem::class)->handle($item, InboxTriageType::ParkingLot);

        $this->assertInstanceOf(Project::class, $project);
        $this->assertSame('Parking Lot', $project->area->name);
        $this->assertSame(ProjectStatus::Paused, $project->status);
        $this->assertSame($text, $project->notes);
        $this->assertLessThanOrEqual(255, mb_strlen($project->title));
        $this->assertNotNull($item->fresh()->triaged_at);
    }

    public function test_invalid_task_destination_rolls_back_without_triaging_the_item(): void
    {
        $item = InboxItem::create(['text' => 'An idea']);
        try {
            app(TriageInboxItem::class)->handle($item, InboxTriageType::Task, []);
            $this->fail('Expected task validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('title', $exception->errors());
        }

        $this->assertDatabaseCount('tasks', 0);
        $this->assertNull($item->fresh()->triaged_at);
    }

    public function test_triaging_an_item_twice_does_not_create_a_second_destination(): void
    {
        $item = InboxItem::create(['text' => 'An idea']);
        $action = app(TriageInboxItem::class);
        $action->handle($item, InboxTriageType::Task, ['title' => 'An idea']);

        try {
            $action->handle($item, InboxTriageType::ParkingLot);
            $this->fail('Expected duplicate triage to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('inbox_item', $exception->errors());
        }

        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_missing_parking_lot_area_rolls_back_and_keeps_item_in_inbox(): void
    {
        Area::where('name', 'Parking Lot')->delete();
        $item = InboxItem::create(['text' => 'A thought']);

        $this->expectException(ModelNotFoundException::class);
        try {
            app(TriageInboxItem::class)->handle($item, InboxTriageType::ParkingLot);
        } finally {
            $this->assertNull($item->fresh()->triaged_at);
            $this->assertDatabaseCount('projects', 0);
        }
    }

    private function createProject(string $title, ?string $notes = null): Project
    {
        return app(CreateProject::class)->handle([
            'area_id' => Area::where('name', 'Dissertation')->value('id'),
            'title' => $title,
            'status' => ProjectStatus::Active,
            'notes' => $notes,
        ], ['title' => 'Existing next action']);
    }
}
