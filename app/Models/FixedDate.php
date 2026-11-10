<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['area_id', 'title', 'date'])]
class FixedDate extends DashboardModel
{
    protected function casts(): array
    {
        return ['area_id' => 'integer', 'date' => 'immutable_date'];
    }

    protected function rules(): array
    {
        return [
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
