<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\DocumentTemplateType;
use Database\Factories\GeneratedLetterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int|null $document_template_id
 * @property int $employee_id
 * @property int|null $employee_document_id
 * @property DocumentTemplateType $type
 * @property string $subject
 * @property string $body
 * @property array<string, mixed>|null $context
 * @property int|null $generated_by
 * @property-read Employee $employee
 */
class GeneratedLetter extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<GeneratedLetterFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentTemplateType::class,
            'context' => 'array',
        ];
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

    /**
     * @return BelongsTo<DocumentTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }
}
