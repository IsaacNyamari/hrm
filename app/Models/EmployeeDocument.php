<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\DocumentCategory;
use Carbon\CarbonImmutable;
use Database\Factories\EmployeeDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int $employee_id
 * @property DocumentCategory $category
 * @property int|null $document_type_id
 * @property array<int, int>|null $expiry_reminders_sent
 * @property string $title
 * @property string $original_filename
 * @property string $disk
 * @property string $path
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property CarbonImmutable|null $issue_date
 * @property CarbonImmutable|null $expiry_date
 * @property bool $is_confidential
 * @property int|null $uploaded_by
 */
class EmployeeDocument extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EmployeeDocumentFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'size_bytes' => 'integer',
            'issue_date' => 'immutable_date',
            'expiry_date' => 'immutable_date',
            'expiry_reminders_sent' => 'array',
            'is_confidential' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isExpiringWithin(int $days): bool
    {
        return $this->expiry_date !== null
            && $this->expiry_date->isFuture()
            && $this->expiry_date->diffInDays(CarbonImmutable::now()) <= $days;
    }
}
