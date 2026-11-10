<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Validation\Rule;

#[Fillable(['date', 'wins', 'carried_over', 'notes'])]
class DailyReview extends DashboardModel
{
    protected $attributes = ['carried_over' => '[]'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date', 'carried_over' => 'array'];
    }

    protected function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('daily_reviews')->ignore($this->id)],
            'wins' => ['nullable', 'string'],
            'carried_over' => ['required', 'json'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
