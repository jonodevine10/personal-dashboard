<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['description', 'who', 'since_date', 'chase_on'])]
class WaitingOn extends DashboardModel
{
    protected function casts(): array
    {
        return ['since_date' => 'immutable_date', 'chase_on' => 'immutable_date'];
    }

    protected function rules(): array
    {
        return [
            'description' => ['required', 'string'],
            'who' => ['required', 'string', 'max:255'],
            'since_date' => ['required', 'date_format:Y-m-d'],
            'chase_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
