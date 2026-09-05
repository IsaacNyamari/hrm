<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Database\Factories\EmploymentTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property bool $is_system
 * @property bool $is_active
 */
class EmploymentType extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EmploymentTypeFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * The seven defaults every tenant starts with (all customisable / extendable).
     *
     * @return array<int, string>
     */
    public static function defaults(): array
    {
        return ['Permanent', 'Contract', 'Temporary', 'Intern', 'Part-time', 'Casual', 'Consultant'];
    }
}
