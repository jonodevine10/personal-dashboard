<?php

namespace App\Actions;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionTask
{
    public function handle(
        Task $task,
        TaskStatus $status,
        ?Task $replacement = null,
        ?ProjectStatus $projectStatus = null,
    ): Task {
        return DB::transaction(function () use ($task, $status, $replacement, $projectStatus): Task {
            $task->refresh();
            $project = Project::where('next_action_task_id', $task->id)->first();

            if ($status !== TaskStatus::Pending && $project !== null && $project->status === ProjectStatus::Active) {
                if ($replacement !== null) {
                    $replacement->refresh();
                }

                if ($replacement?->id === $task->id
                    || ($replacement === null && ! in_array($projectStatus, [ProjectStatus::Paused, ProjectStatus::Done], true))) {
                    throw ValidationException::withMessages([
                        'next_action_task_id' => 'Choose a replacement task or pause/finish the project.',
                    ]);
                }

                $project->fill([
                    'next_action_task_id' => $replacement?->id,
                    'status' => $projectStatus ?? ProjectStatus::Active,
                ])->save();
            }

            $task->status = $status;
            $task->save();

            return $task->refresh();
        });
    }
}
