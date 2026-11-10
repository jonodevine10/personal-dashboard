<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

#[Fillable(['name', 'colour', 'sort_order'])]
class Area extends DashboardModel
{
    protected $attributes = ['sort_order' => 0];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('areas')->ignore($this->id)],
            'colour' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<Commitment, $this> */
    public function commitments(): HasMany
    {
        return $this->hasMany(Commitment::class);
    }

    /** @return HasMany<FixedDate, $this> */
    public function fixedDates(): HasMany
    {
        return $this->hasMany(FixedDate::class);
    }
}
