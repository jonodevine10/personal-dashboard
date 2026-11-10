<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

#[Fillable(['area_id', 'title', 'days_of_week', 'start_time', 'end_time', 'is_enabled'])]
class Commitment extends DashboardModel
{
    protected $attributes = ['days_of_week' => '[]', 'is_enabled' => true];

    protected function casts(): array
    {
        return ['area_id' => 'integer', 'days_of_week' => 'array', 'is_enabled' => 'boolean'];
    }

    protected function rules(): array
    {
        return [
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'title' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'is_enabled' => ['required', 'boolean'],
        ];
    }

    protected function validateForSave(): void
    {
        parent::validateForSave();

        $daysOfWeek = array_map(
            fn (mixed $day): mixed => is_numeric($day) ? (int) $day : $day,
            $this->days_of_week ?? [],
        );

        Validator::make(['days_of_week' => $daysOfWeek], [
            'days_of_week' => [$this->is_enabled ? 'required' : 'present', 'array', 'list', 'max:7'],
            'days_of_week.*' => ['required', 'integer', 'between:1,7', 'distinct'],
        ])->validate();

        $this->days_of_week = $daysOfWeek;
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
