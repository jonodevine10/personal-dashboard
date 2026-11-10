<?php

namespace App\Actions;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProject
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $nextAction
     */
    public function handle(array $attributes, ?array $nextAction = null): Project
    {
        return DB::transaction(function () use ($attributes, $nextAction): Project {
            $project = new Project($attributes);
            $desiredStatus = $project->status;

            if ($desiredStatus === ProjectStatus::Active && $nextAction === null) {
                throw ValidationException::withMessages([
                    'next_action_task_id' => 'An active project requires a next-action task.',
                ]);
            }

            $project->status = ProjectStatus::Paused;
            $project->next_action_task_id = null;
            $project->save();

            if ($nextAction !== null) {
                $task = $project->tasks()->create([
                    ...$nextAction,
                    'project_id' => $project->id,
                ]);
                $project->next_action_task_id = $task->id;
            }

            $project->status = $desiredStatus;
            $project->save();

            return $project->refresh();
        });
    }
}
