<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

abstract class DashboardModel extends Model
{
    public function setAttribute($key, $value)
    {
        // Eloquent normally stores date casts with a time component. Calendar
        // dates must remain YYYY-MM-DD for SQLite comparisons and indexes.
        if (($this->getCasts()[$key] ?? null) === 'immutable_date') {
            $this->attributes[$key] = $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    protected static function booted(): void
    {
        static::saving(fn (DashboardModel $model) => $model->validateForSave());
    }

    /** @return array<string, mixed> */
    abstract protected function rules(): array;

    protected function validateForSave(): void
    {
        Validator::make($this->getAttributes(), $this->rules())->validate();
    }
}
