<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Database\Factories\JobPositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $title
 * @property string $code
 * @property int|null $department_id
 * @property int|null $designation_id
 * @property int|null $employment_type_id
 * @property string|null $description
 * @property string|null $requirements
 * @property bool $is_active
 */
class JobPosition extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<JobPositionFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<Vacancy, $this>
     */
    public function vacancies(): HasMany
    {
        return $this->hasMany(Vacancy::class);
    }
}
