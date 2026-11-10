<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[Fillable(['area_id', 'title', 'status', 'next_action_task_id', 'notes', 'target_date'])]
class Project extends DashboardModel
{
    protected $attributes = ['status' => 'paused'];

    protected function casts(): array
    {
        return [
            'area_id' => 'integer',
            'next_action_task_id' => 'integer',
            'status' => ProjectStatus::class,
            'target_date' => 'immutable_date',
        ];
    }

    protected function rules(): array
    {
        return [
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'next_action_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'notes' => ['nullable', 'string'],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    protected function validateForSave(): void
    {
        parent::validateForSave();

        $task = $this->next_action_task_id === null ? null : Task::find($this->next_action_task_id);

        if (($this->status === ProjectStatus::Active && ($task === null || $task->status !== TaskStatus::Pending))
            || ($task !== null && $task->project_id !== $this->id)) {
            throw ValidationException::withMessages([
                'next_action_task_id' => 'Choose a pending task belonging to this project before making it active.',
            ]);
        }
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function nextActionTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'next_action_task_id');
    }
}
