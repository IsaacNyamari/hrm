<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int|null $parent_id
 * @property int|null $branch_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property int|null $head_employee_id
 * @property bool $is_active
 * @property-read Department|null $parent
 */
class Department extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    /**
     * @return HasMany<Designation, $this>
     */
    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class);
    }

    /**
     * IDs of this department and every department beneath it. Used to reject
     * circular parenting.
     *
     * @return array<int, int>
     */
    public function subtreeIds(): array
    {
        $ids = [$this->getKey()];
        $queue = [$this->getKey()];

        while ($queue !== []) {
            $children = static::query()
                ->whereIn('parent_id', $queue)
                ->pluck('id')
                ->all();

            $queue = array_values(array_diff($children, $ids));
            $ids = array_merge($ids, $queue);
        }

        return $ids;
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
