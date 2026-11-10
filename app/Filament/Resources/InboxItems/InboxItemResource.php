<?php

namespace App\Filament\Resources\InboxItems;

use App\Actions\TriageInboxItem;
use App\Enums\InboxTriageType;
use App\Enums\TaskTier;
use App\Filament\Resources\InboxItems\Pages\ManageInboxItems;
use App\Models\InboxItem;
use App\Models\Project;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class InboxItemResource extends Resource
{
    protected static ?string $model = InboxItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('text')
                    ->required()
                    ->maxLength(5000)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('text')
                    ->label('Captured item')
                    ->searchable()
                    ->wrap()
                    ->limit(180),
                TextColumn::make('triaged_at')
                    ->label('Triaged')
                    ->dateTime('D j M, H:i')
                    ->placeholder('Needs triage')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Captured')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('triage_state')
                    ->label('Inbox status')
                    ->options([
                        'pending' => 'Needs triage',
                        'triaged' => 'Triaged history',
                    ])
                    ->default('pending')
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'pending' => $query->whereNull('triaged_at'),
                        'triaged' => $query->whereNotNull('triaged_at'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('triageTask')
                    ->label('Make task')
                    ->visible(fn (InboxItem $record): bool => $record->triaged_at === null)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->default(fn (InboxItem $record): string => Str::limit(trim($record->text), 255, '')),
                        Select::make('project_id')
                            ->label('Project (optional)')
                            ->options(fn (): array => Project::query()->orderBy('title')->pluck('title', 'id')->all())
                            ->searchable(),
                        Select::make('tier')
                            ->options([
                                TaskTier::Must->value => 'Must',
                                TaskTier::Should->value => 'Should',
                                TaskTier::Could->value => 'Could',
                            ])
                            ->default(TaskTier::Should->value)
                            ->required(),
                        DatePicker::make('scheduled_for')
                            ->label('Schedule for (optional)')
                            ->native(true),
                        TextInput::make('estimate_minutes')
                            ->label('Estimate (minutes, optional)')
                            ->numeric()
                            ->minValue(1),
                    ])
                    ->modalSubmitActionLabel('Create task')
                    ->action(fn (InboxItem $record, array $data) => app(TriageInboxItem::class)->handle(
                        $record,
                        InboxTriageType::Task,
                        $data,
                    )),
                Action::make('triageProjectNote')
                    ->label('Add project note')
                    ->visible(fn (InboxItem $record): bool => $record->triaged_at === null)
                    ->schema([
                        Select::make('project_id')
                            ->label('Project')
                            ->options(fn (): array => Project::query()->orderBy('title')->pluck('title', 'id')->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->modalSubmitActionLabel('Append note')
                    ->action(fn (InboxItem $record, array $data) => app(TriageInboxItem::class)->handle(
                        $record,
                        InboxTriageType::ProjectNote,
                        $data,
                    )),
                Action::make('triageParkingLot')
                    ->label('Move to Parking Lot')
                    ->visible(fn (InboxItem $record): bool => $record->triaged_at === null)
                    ->requiresConfirmation()
                    ->modalDescription('Create a paused project in Parking Lot using this captured text.')
                    ->action(fn (InboxItem $record) => app(TriageInboxItem::class)->handle(
                        $record,
                        InboxTriageType::ParkingLot,
                    )),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInboxItems::route('/'),
        ];
    }
}
