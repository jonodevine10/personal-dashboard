<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['text', 'triaged_at'])]
class InboxItem extends DashboardModel
{
    protected function casts(): array
    {
        return ['triaged_at' => 'immutable_datetime'];
    }

    protected function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:5000'],
            'triaged_at' => ['nullable', 'date'],
        ];
    }
}
