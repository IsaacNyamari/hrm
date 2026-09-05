<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int|null $user_id
 * @property string $original_filename
 * @property string $storage_path
 * @property string $status
 * @property int $total_rows
 * @property int $imported_rows
 * @property int $failed_rows
 * @property array<int, array{row: int, errors: array<int, string>}>|null $errors
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 */
class EmployeeImport extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
