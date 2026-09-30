<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Support\Auditor;
use Illuminate\Database\Eloquent\Model;

/**
 * Records create / update / delete of the model to the audit log. The event
 * name is `{table-singular}.{verb}` (e.g. `employee.updated`). Columns listed
 * in {@see auditExclude()} are stripped from the recorded change set.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            self::audit($model, 'created', ['attributes' => self::auditableAttributes($model, $model->getAttributes())]);
        });

        static::updated(function (Model $model): void {
            $changes = self::auditableAttributes($model, $model->getChanges());
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            self::audit($model, 'updated', [
                'changes' => $changes,
                'original' => self::auditableAttributes($model, array_intersect_key($model->getOriginal(), $changes)),
            ]);
        });

        static::deleted(function (Model $model): void {
            self::audit($model, 'deleted', []);
        });
    }

    /**
     * Attribute names never written to the audit trail.
     *
     * @return array<int, string>
     */
    protected function auditExclude(): array
    {
        return ['password', 'remember_token', 'secret', 'two_factor_secret', 'two_factor_recovery_codes'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function auditableAttributes(Model $model, array $attributes): array
    {
        /** @var array<int, string> $exclude */
        $exclude = method_exists($model, 'auditExclude') ? $model->auditExclude() : [];

        return array_diff_key($attributes, array_flip($exclude));
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private static function audit(Model $model, string $verb, array $properties): void
    {
        if (Auditor::isMuted()) {
            return;
        }

        $name = str(class_basename($model))->snake()->value();

        app(Auditor::class)->log("{$name}.{$verb}", $model, $properties);
    }
}
