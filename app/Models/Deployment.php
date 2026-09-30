<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\DeploymentStatus;
use App\Enums\DeploymentType;
use Database\Factories\DeploymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single "pull latest changes" or "roll back" run against the app's own
 * git working copy, triggered from the platform admin's Deployments page and
 * executed in the background by App\Jobs\RunDeployment. Platform-wide, not
 * tenant data.
 *
 * @property int $id
 * @property string $uuid
 * @property DeploymentType $type
 * @property DeploymentStatus $status
 * @property string|null $branch
 * @property string|null $from_commit
 * @property string|null $to_commit
 * @property string|null $output
 * @property int|null $triggered_by
 */
class Deployment extends Model
{
    /** @use HasFactory<DeploymentFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DeploymentType::class,
            'status' => DeploymentStatus::class,
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
