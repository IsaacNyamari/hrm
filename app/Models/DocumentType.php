<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $name
 * @property string $code
 * @property bool $requires_expiry
 * @property array<int, int>|null $notify_before_days
 * @property bool $is_active
 */
class DocumentType extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_expiry' => 'boolean',
            'notify_before_days' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<int, int>
     */
    public function reminderMarks(): array
    {
        return array_map('intval', $this->notify_before_days ?? [30, 14, 7]);
    }
}
