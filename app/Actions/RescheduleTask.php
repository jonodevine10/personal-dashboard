<?php

namespace App\Actions;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RescheduleTask
{
    public function handle(Task $task, ?string $date): Task
    {
        return DB::transaction(function () use ($task, $date): Task {
            $task->refresh();

            if ($task->status !== TaskStatus::Pending) {
                throw ValidationException::withMessages([
                    'scheduled_for' => 'Only pending tasks can be rescheduled.',
                ]);
            }

            $task->scheduled_for = $date;
            $task->save();

            return $task->refresh();
        });
    }
}
