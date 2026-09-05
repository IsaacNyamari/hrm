<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Database\Factories\TenantBrandingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string|null $brand_name
 * @property string|null $logo_disk
 * @property string|null $logo_path
 * @property string $primary_color
 * @property string|null $accent_color
 * @property string|null $from_name
 * @property string|null $from_email
 * @property string|null $reply_to_email
 * @property string|null $support_email
 * @property string|null $email_footer
 * @property string|null $email_signature
 */
class TenantBranding extends Model
{
    use Auditable;
    use BelongsToTenant;

    /** @use HasFactory<TenantBrandingFactory> */
    use HasFactory;

    protected $table = 'tenant_branding';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function logoUrl(): ?string
    {
        if ($this->logo_path === null) {
            return null;
        }

        return Storage::disk($this->logo_disk ?? 'public')->url($this->logo_path);
    }

    public function displayName(): string
    {
        if (is_string($this->brand_name) && $this->brand_name !== '') {
            return $this->brand_name;
        }

        $tenantName = data_get($this, 'tenant.name');

        if (is_string($tenantName) && $tenantName !== '') {
            return $tenantName;
        }

        return (string) config('app.name', 'HRM');
    }

    public function fromName(): string
    {
        return $this->from_name ?? $this->displayName();
    }
}
