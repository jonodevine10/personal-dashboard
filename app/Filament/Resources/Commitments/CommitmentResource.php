<?php

namespace App\Filament\Resources\Commitments;

use App\Filament\Resources\Commitments\Pages\ManageCommitments;
use App\Models\Commitment;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommitmentResource extends Resource
{
    protected static ?string $model = Commitment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('area_id')
                    ->relationship('area', 'name')
                    ->required(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                CheckboxList::make('days_of_week')
                    ->label('Days of the week')
                    ->options([
                        1 => 'Monday',
                        2 => 'Tuesday',
                        3 => 'Wednesday',
                        4 => 'Thursday',
                        5 => 'Friday',
                        6 => 'Saturday',
                        7 => 'Sunday',
                    ])
                    ->columns(2)
                    ->required(fn (Get $get): bool => (bool) $get('is_enabled'))
                    ->helperText('Choose the days this commitment starts. Overnight blocks continue into the following day.'),
                TimePicker::make('start_time')
                    ->required()
                    ->seconds(false)
                    ->format('H:i'),
                TimePicker::make('end_time')
                    ->required()
                    ->seconds(false)
                    ->format('H:i'),
                Toggle::make('is_enabled')
                    ->default(false)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('area'))
            ->columns([
                TextColumn::make('area.name')
                    ->label('Area')
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('days_of_week')
                    ->label('Days')
                    ->formatStateUsing(fn (mixed $state): string => is_array($state)
                        ? collect($state)
                            ->map(fn (int|string $day): string => [
                                1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu',
                                5 => 'Fri', 6 => 'Sat', 7 => 'Sun',
                            ][(int) $day] ?? '')
                            ->filter()
                            ->join(', ')
                        : (is_numeric($state) ? [
                            1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu',
                            5 => 'Fri', 6 => 'Sat', 7 => 'Sun',
                        ][(int) $state] ?? '' : (string) $state)),
                TextColumn::make('start_time')
                    ->label('Starts')
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 5) : ''),
                TextColumn::make('end_time')
                    ->label('Ends')
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 5) : ''),
                IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean(),
            ])
            ->defaultSort('title')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCommitments::route('/'),
        ];
    }
}
