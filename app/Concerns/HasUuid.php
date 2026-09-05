<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Support\Str;

/**
 * Assigns an ordered UUID to the model's uuid column on creation.
 *
 * Does not change the route key — models that want UUID route-model binding
 * override getRouteKeyName() themselves.
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function (self $model): void {
            $column = $model->uuidColumn();

            if (empty($model->getAttribute($column))) {
                $model->setAttribute($column, (string) Str::orderedUuid());
            }
        });
    }

    public function uuidColumn(): string
    {
        return 'uuid';
    }
}
