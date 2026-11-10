<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Validation\Rule;

#[Fillable(['date', 'focus_minutes'])]
class DailyCapacity extends DashboardModel
{
    protected function casts(): array
    {
        return ['date' => 'immutable_date', 'focus_minutes' => 'integer'];
    }

    protected function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('daily_capacities')->ignore($this->id)],
            'focus_minutes' => ['required', 'integer', 'min:0'],
        ];
    }
}
