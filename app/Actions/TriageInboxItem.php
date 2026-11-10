<?php

namespace App\Actions;

use App\Enums\InboxTriageType;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskTier;
use App\Models\Area;
use App\Models\InboxItem;
use App\Models\Project;
use App\Models\Task;
use App\Services\DashboardClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TriageInboxItem
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(InboxItem $inboxItem, InboxTriageType $type, array $data = []): Model
    {
        return DB::transaction(function () use ($inboxItem, $type, $data): Model {
            $item = InboxItem::query()->lockForUpdate()->findOrFail($inboxItem->id);

            if ($item->triaged_at !== null) {
                throw ValidationException::withMessages([
                    'inbox_item' => 'This item has already been triaged.',
                ]);
            }

            $destination = match ($type) {
                InboxTriageType::Task => $this->createTask($item, $data),
                InboxTriageType::ProjectNote => $this->appendProjectNote($item, $data),
                InboxTriageType::ParkingLot => $this->createParkingLotProject($item),
            };

            $item->triaged_at = now();
            $item->save();

            return $destination;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createTask(InboxItem $item, array $data): Task
    {
        $validated = Validator::make([
            'tier' => TaskTier::Should->value,
            ...$data,
        ], [
            'title' => ['required', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'tier' => ['required', Rule::enum(TaskTier::class)],
            'scheduled_for' => ['nullable', 'date_format:Y-m-d'],
            'estimate_minutes' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        return Task::create([
            ...$validated,
            'tier' => $validated['tier'],
            'status' => TaskStatus::Pending,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function appendProjectNote(InboxItem $item, array $data): Project
    {
        $validated = Validator::make($data, [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
        ])->validate();

        $project = Project::query()->lockForUpdate()->findOrFail($validated['project_id']);
        $timestamp = now()->setTimezone(app(DashboardClock::class)->timezone())->format('D j M Y, H:i');
        $note = "**Inbox note — {$timestamp}**\n\n{$item->text}";
        $project->notes = filled($project->notes) ? "{$project->notes}\n\n---\n\n{$note}" : $note;
        $project->save();

        return $project;
    }

    private function createParkingLotProject(InboxItem $item): Project
    {
        $area = Area::query()->where('name', 'Parking Lot')->firstOrFail();

        return app(CreateProject::class)->handle([
            'area_id' => $area->id,
            'title' => Str::limit(trim($item->text), 255, ''),
            'status' => ProjectStatus::Paused,
            'notes' => $item->text,
        ]);
    }
}
