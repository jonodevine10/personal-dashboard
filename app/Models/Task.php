<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[Fillable(['project_id', 'title', 'tier', 'due_date', 'scheduled_for', 'estimate_minutes', 'status'])]
class Task extends DashboardModel
{
    protected $attributes = ['tier' => 'should', 'status' => 'pending', 'carry_count' => 0];

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'tier' => TaskTier::class,
            'status' => TaskStatus::class,
            'due_date' => 'immutable_date',
            'scheduled_for' => 'immutable_date',
            'estimate_minutes' => 'integer',
            'completed_at' => 'immutable_datetime',
            'carry_count' => 'integer',
        ];
    }

    protected function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'tier' => ['required', Rule::enum(TaskTier::class)],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'scheduled_for' => ['nullable', 'date_format:Y-m-d'],
            'estimate_minutes' => ['nullable', 'integer', 'min:1'],
            'carry_count' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function validateForSave(): void
    {
        parent::validateForSave();

        foreach ($this->exists ? Project::where('next_action_task_id', $this->id)->get() : [] as $project) {
            if ($this->project_id !== $project->id
                || ($project->status === ProjectStatus::Active && $this->status !== TaskStatus::Pending)) {
                throw ValidationException::withMessages([
                    'status' => 'Replace the next action or pause/finish its project first.',
                ]);
            }
        }

        $this->completed_at = $this->status === TaskStatus::Done
            ? ($this->completed_at ?? now())
            : null;

        $previousDate = $this->getRawOriginal('scheduled_for');
        if ($this->exists && $this->status === TaskStatus::Pending
            && $this->getRawOriginal('status') === TaskStatus::Pending->value && $this->isDirty('scheduled_for')
            && $previousDate !== null && $this->scheduled_for !== null
            && $this->scheduled_for->toDateString() > $previousDate) {
            $this->carry_count = $this->getRawOriginal('carry_count') + 1;
        }
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
