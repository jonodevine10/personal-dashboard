<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['default_focus_minutes'])]
class DashboardSetting extends DashboardModel
{
    public $incrementing = false;

    protected $attributes = ['id' => 1, 'default_focus_minutes' => 240];

    protected function casts(): array
    {
        return ['default_focus_minutes' => 'integer'];
    }

    protected function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'in:1'],
            'default_focus_minutes' => ['required', 'integer', 'min:0'],
        ];
    }
}
