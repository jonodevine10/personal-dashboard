<?php

namespace Tests\Feature;

use App\Actions\CreateProject;
use App\Actions\RescheduleTask;
use App\Actions\TransitionTask;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskTier;
use App\Models\Area;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectTaskTest extends TestCase
{
    use RefreshDatabase;

    private function project(): Project
    {
        $area = Area::create(['name' => 'Builds', 'colour' => '#3B82F6']);

        return app(CreateProject::class)->handle([
            'area_id' => $area->id, 'title' => 'Build dashboard', 'status' => ProjectStatus::Active,
        ], ['title' => 'Create models', 'scheduled_for' => '2026-11-07']);
    }

    private function assertValidation(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_an_active_project_is_created_with_its_own_pending_next_action(): void
    {
        $project = $this->project();
        $this->assertSame(ProjectStatus::Active, $project->status);
        $this->assertSame($project->id, $project->nextActionTask->project_id);
        $this->assertSame(TaskStatus::Pending, $project->nextActionTask->status);
        $this->assertSame(TaskTier::Should, $project->nextActionTask->tier);
        $this->assertSame('2026-11-07', $project->nextActionTask->getRawOriginal('scheduled_for'));
    }

    public function test_active_project_creation_without_a_next_action_fails_without_partial_records(): void
    {
        $area = Area::create(['name' => 'Builds', 'colour' => '#3B82F6']);
        $this->assertValidation(fn () => app(CreateProject::class)->handle([
            'area_id' => $area->id, 'title' => 'No action', 'status' => ProjectStatus::Active,
        ]), 'next_action_task_id');
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_invalid_next_task_rolls_back_project_creation(): void
    {
        $area = Area::create(['name' => 'Builds', 'colour' => '#3B82F6']);
        $this->assertValidation(fn () => app(CreateProject::class)->handle([
            'area_id' => $area->id, 'title' => 'No action', 'status' => ProjectStatus::Active,
        ], ['title' => '']), 'title');
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_active_project_save_cannot_clear_its_next_action(): void
    {
        $project = $this->project();
        $this->assertValidation(fn () => $project->update(['next_action_task_id' => null]), 'next_action_task_id');
        $this->assertNotNull($project->fresh()->next_action_task_id);
    }

    public function test_another_projects_task_cannot_be_a_next_action(): void
    {
        $project = $this->project();
        $unassigned = Task::create(['title' => 'Unassigned task']);
        $this->assertValidation(fn () => $project->update(['next_action_task_id' => $unassigned->id]), 'next_action_task_id');
    }

    public function test_direct_completion_drop_and_reassignment_are_rejected(): void
    {
        $task = $this->project()->nextActionTask;
        foreach ([['status' => TaskStatus::Done], ['status' => TaskStatus::Dropped], ['project_id' => null]] as $change) {
            $this->assertValidation(fn () => $task->refresh()->update($change), 'status');
        }
        $this->assertSame(TaskStatus::Pending, $task->fresh()->status);
    }

    public function test_completion_requires_a_replacement_or_project_disposition(): void
    {
        $task = $this->project()->nextActionTask;
        $this->assertValidation(fn () => app(TransitionTask::class)->handle($task, TaskStatus::Done), 'next_action_task_id');
        $this->assertSame(TaskStatus::Pending, $task->fresh()->status);
    }

    public function test_completing_a_next_action_and_replacing_it_is_atomic(): void
    {
        $project = $this->project();
        $task = $project->nextActionTask;
        $replacement = $project->tasks()->create(['title' => 'Build Today']);
        app(TransitionTask::class)->handle($task, TaskStatus::Done, $replacement);
        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame($replacement->id, $project->fresh()->next_action_task_id);
        $this->assertSame(ProjectStatus::Active, $project->fresh()->status);
    }

    public function test_invalid_replacement_leaves_project_and_task_unchanged(): void
    {
        $project = $this->project();
        $task = $project->nextActionTask;
        $replacement = Task::create(['title' => 'Unassigned']);
        $this->assertValidation(
            fn () => app(TransitionTask::class)->handle($task, TaskStatus::Done, $replacement),
            'next_action_task_id',
        );
        $this->assertSame($task->id, $project->fresh()->next_action_task_id);
        $this->assertSame(TaskStatus::Pending, $task->fresh()->status);
    }

    public function test_a_completed_task_cannot_be_the_next_action_of_an_active_project(): void
    {
        $project = $this->project();
        $replacement = $project->tasks()->create(['title' => 'Already done', 'status' => TaskStatus::Done]);
        $this->assertValidation(fn () => $project->update(['next_action_task_id' => $replacement->id]), 'next_action_task_id');
    }

    public function test_finishing_or_pausing_allows_next_action_completion_or_drop(): void
    {
        $project = $this->project();
        app(TransitionTask::class)->handle($project->nextActionTask, TaskStatus::Done, projectStatus: ProjectStatus::Done);
        $this->assertSame(ProjectStatus::Done, $project->fresh()->status);
        $this->assertNull($project->fresh()->next_action_task_id);

        $other = app(CreateProject::class)->handle([
            'area_id' => $project->area_id, 'title' => 'Other', 'status' => ProjectStatus::Active,
        ], ['title' => 'Pause this']);
        app(TransitionTask::class)->handle($other->nextActionTask, TaskStatus::Dropped, projectStatus: ProjectStatus::Paused);
        $this->assertSame(ProjectStatus::Paused, $other->fresh()->status);
        $this->assertNull($other->fresh()->next_action_task_id);
    }

    public function test_carry_count_increments_only_once_for_each_later_date_move(): void
    {
        $task = Task::create(['title' => 'Read']);
        $reschedule = app(RescheduleTask::class);
        $reschedule->handle($task, '2026-11-07');
        $this->assertSame(0, $task->carry_count);
        $reschedule->handle($task, '2026-11-08');
        $this->assertSame(1, $task->carry_count);
        $reschedule->handle($task, '2026-11-08');
        $this->assertSame(1, $task->carry_count);
        $reschedule->handle($task, '2026-11-07');
        $this->assertSame(1, $task->carry_count);
        $reschedule->handle($task, '2026-11-09');
        $this->assertSame(2, $task->carry_count);
        $reschedule->handle($task, null);
        $this->assertSame(2, $task->carry_count);
        app(TransitionTask::class)->handle($task, TaskStatus::Done);
        $this->assertSame(2, $task->fresh()->carry_count);
    }

    public function test_direct_resource_style_rescheduling_uses_the_same_counter_rule(): void
    {
        $task = Task::create(['title' => 'Read', 'scheduled_for' => '2026-11-07']);
        $task->update(['scheduled_for' => '2026-11-08']);
        $this->assertSame(1, $task->fresh()->carry_count);
    }

    public function test_reopening_and_scheduling_a_completed_task_does_not_count_as_carry_over(): void
    {
        $task = Task::create([
            'title' => 'Read', 'scheduled_for' => '2026-11-07', 'status' => TaskStatus::Done,
        ]);
        $task->update(['scheduled_for' => '2026-11-08', 'status' => TaskStatus::Pending]);
        $this->assertSame(0, $task->fresh()->carry_count);
    }

    public function test_completed_tasks_cannot_be_rescheduled(): void
    {
        $task = Task::create(['title' => 'Read', 'status' => TaskStatus::Done]);
        $this->assertValidation(fn () => app(RescheduleTask::class)->handle($task, '2026-11-08'), 'scheduled_for');
    }

    public function test_string_foreign_keys_from_forms_are_valid_for_linked_next_actions(): void
    {
        $project = $this->project();
        $task = $project->nextActionTask;
        $task->update(['project_id' => (string) $project->id]);
        $project->update(['next_action_task_id' => (string) $task->id]);
        $this->assertSame($task->id, $project->fresh()->next_action_task_id);
    }

    public function test_database_rejects_a_next_action_from_a_different_project(): void
    {
        $project = $this->project();
        $other = Task::create(['title' => 'Unassigned']);
        $this->expectException(QueryException::class);
        DB::table('projects')->where('id', $project->id)->update(['next_action_task_id' => $other->id]);
    }

    public function test_a_project_with_tasks_cannot_be_deleted(): void
    {
        $project = $this->project();
        $this->expectException(QueryException::class);
        $project->delete();
    }

    public function test_completion_timestamp_is_cleared_on_reopen_or_drop(): void
    {
        $task = Task::create(['title' => 'Read', 'status' => TaskStatus::Done]);
        $this->assertNotNull($task->completed_at);
        app(TransitionTask::class)->handle($task, TaskStatus::Pending);
        $this->assertNull($task->completed_at);
        app(TransitionTask::class)->handle($task, TaskStatus::Dropped);
        $this->assertNull($task->completed_at);
    }

    public function test_database_rejects_bulk_next_action_completion(): void
    {
        $task = $this->project()->nextActionTask;
        $this->expectException(QueryException::class);
        DB::table('tasks')->where('id', $task->id)->update(['status' => 'done', 'completed_at' => now()]);
    }

    public function test_database_rejects_bulk_clearing_an_active_next_action(): void
    {
        $project = $this->project();
        $this->expectException(QueryException::class);
        DB::table('projects')->where('id', $project->id)->update(['next_action_task_id' => null]);
    }

    public function test_referenced_next_action_cannot_be_deleted(): void
    {
        $task = $this->project()->nextActionTask;
        $this->expectException(QueryException::class);
        $task->delete();
    }

    public function test_referenced_area_cannot_be_deleted(): void
    {
        $project = $this->project();
        $this->expectException(QueryException::class);
        $project->area->delete();
    }
}
