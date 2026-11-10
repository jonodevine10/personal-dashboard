<?php

namespace App\Filament\Widgets;

use App\Actions\RescheduleTask;
use App\Actions\TransitionTask;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskTier;
use App\Models\Project;
use App\Models\Task;
use App\Services\DashboardClock;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TodayTasks extends TableWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Today’s tasks')
            ->query(fn (): Builder => Task::query()
                ->with('project')
                ->whereDate('scheduled_for', app(DashboardClock::class)->today()->toDateString())
                ->where('status', TaskStatus::Pending))
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('project.title')
                    ->label('Project')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('estimate_minutes')
                    ->label('Estimate')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? 'Estimate needed' : "{$state} min")
                    ->color(fn (?int $state): string => $state === null ? 'warning' : 'gray'),
                TextColumn::make('due_date')
                    ->date()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('carry_count')
                    ->label('Carried')
                    ->formatStateUsing(fn (int $state): ?string => $state > 0 ? "Carried {$state}×" : null)
                    ->badge()
                    ->color('warning')
                    ->placeholder('—'),
            ])
            ->groups([
                Group::make('tier')
                    ->label('Priority')
                    ->getTitleFromRecordUsing(fn (Task $record): string => ucfirst($record->tier->value))
                    ->orderQueryUsing(function (Builder $query, string $direction): Builder {
                        $safeDirection = $direction === 'desc' ? 'DESC' : 'ASC';

                        return $query->orderByRaw(
                            "CASE tier WHEN 'must' THEN 0 WHEN 'should' THEN 1 ELSE 2 END {$safeDirection}",
                        );
                    }),
            ])
            ->defaultGroup('tier')
            ->groupingSettingsHidden()
            ->paginated(false)
            ->emptyStateHeading('Nothing scheduled for today')
            ->emptyStateDescription('Add a task here, or capture something to your Inbox.')
            ->headerActions([
                CreateAction::make()
                    ->label('Add task')
                    ->model(Task::class)
                    ->form([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->autofocus(),
                        Select::make('tier')
                            ->options([
                                TaskTier::Must->value => 'Must',
                                TaskTier::Should->value => 'Should',
                                TaskTier::Could->value => 'Could',
                            ])
                            ->default(TaskTier::Should->value)
                            ->required(),
                        Select::make('project_id')
                            ->relationship('project', 'title')
                            ->searchable()
                            ->preload(),
                        DatePicker::make('scheduled_for')
                            ->default(fn (): string => app(DashboardClock::class)->today()->toDateString())
                            ->required()
                            ->native(true),
                        TextInput::make('estimate_minutes')
                            ->label('Estimate (minutes)')
                            ->numeric()
                            ->minValue(1),
                    ])
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'status' => TaskStatus::Pending->value,
                    ])
                    ->after(fn () => $this->dispatch('dashboard-data-changed')),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label('Done')
                    ->icon(Heroicon::Check)
                    ->color('success')
                    ->schema(fn (Task $record): array => $this->isActiveNextAction($record) ? [
                        TextInput::make('replacement_title')
                            ->label('Replacement next action')
                            ->helperText('Leave this blank if you are pausing or finishing the project.')
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => blank($get('project_disposition'))),
                        Select::make('project_disposition')
                            ->label('Or change project status')
                            ->options([
                                ProjectStatus::Paused->value => 'Pause project',
                                ProjectStatus::Done->value => 'Finish project',
                            ])
                            ->required(fn (Get $get): bool => blank($get('replacement_title'))),
                    ] : [])
                    ->action(function (Task $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $project = Project::query()->where('next_action_task_id', $record->id)->first();
                            $replacement = null;
                            $projectStatus = null;

                            if ($project?->status === ProjectStatus::Active) {
                                $replacementTitle = trim((string) ($data['replacement_title'] ?? ''));
                                $disposition = $data['project_disposition'] ?? null;

                                if (($replacementTitle === '') === blank($disposition)) {
                                    throw ValidationException::withMessages([
                                        'replacement_title' => 'Enter a replacement next action or choose to pause/finish the project.',
                                    ]);
                                }

                                if ($replacementTitle !== '') {
                                    $replacement = $project->tasks()->create(['title' => $replacementTitle]);
                                } else {
                                    $projectStatus = ProjectStatus::from($disposition);
                                }
                            }

                            app(TransitionTask::class)->handle(
                                $record,
                                TaskStatus::Done,
                                $replacement,
                                $projectStatus,
                            );
                        });

                        Notification::make()->title('Task completed')->success()->send();
                        $this->dispatch('dashboard-data-changed');
                    }),
                Action::make('reschedule')
                    ->label('Reschedule')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->schema([
                        DatePicker::make('scheduled_for')
                            ->label('Move to')
                            ->required()
                            ->native(true),
                    ])
                    ->action(function (Task $record, array $data): void {
                        app(RescheduleTask::class)->handle($record, $data['scheduled_for']);

                        Notification::make()->title('Task rescheduled')->success()->send();
                        $this->dispatch('dashboard-data-changed');
                    }),
            ]);
    }

    private function isActiveNextAction(Task $task): bool
    {
        return Project::query()
            ->whereKey($task->project_id)
            ->where('next_action_task_id', $task->id)
            ->where('status', ProjectStatus::Active)
            ->exists();
    }
}
