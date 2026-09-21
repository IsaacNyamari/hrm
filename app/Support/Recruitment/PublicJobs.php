<?php

declare(strict_types=1);

namespace App\Support\Recruitment;

use App\Enums\TenantStatus;
use App\Enums\VacancyStatus;
use App\Models\Tenant;
use App\Models\Vacancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The public, cross-organization job board on the main website: open,
 * unexpired vacancies of organizations with an active subscription.
 */
class PublicJobs
{
    /**
     * @return Collection<int, Vacancy>
     */
    public function list(string $search = '', ?string $organization = null, bool $remoteOnly = false): Collection
    {
        $term = trim($search);

        return $this->query()
            ->when($term !== '', function (Builder $q) use ($term): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where(fn (Builder $w) => $w->where('title', 'like', $like)->orWhere('location', 'like', $like)->orWhere('description', 'like', $like));
            })
            ->when($organization, fn (Builder $q) => $q->whereHas('tenant', fn (Builder $t) => $t->where('slug', $organization)))
            ->when($remoteOnly, fn (Builder $q) => $q->where('is_remote', true))
            ->with($this->relations())
            ->orderByDesc('published_at')
            ->get()
            ->filter(fn (Vacancy $vacancy): bool => $vacancy->tenant->hasAppAccess())
            ->values();
    }

    public function find(string $organizationSlug, string $vacancySlug): ?Vacancy
    {
        $vacancy = $this->query()
            ->whereHas('tenant', fn (Builder $t) => $t->where('slug', $organizationSlug))
            ->where('slug', $vacancySlug)
            ->with($this->relations())
            ->first();

        return $vacancy !== null && $vacancy->tenant->hasAppAccess() ? $vacancy : null;
    }

    /**
     * Organizations that currently have jobs listed, for the filter.
     *
     * @return Collection<int, Tenant>
     */
    public function organizations(): Collection
    {
        return $this->list()->pluck('tenant')->unique('id')->sortBy('name')->values();
    }

    /**
     * Relations loaded across tenants — a signed-in visitor from another
     * organization must still see them, so tenant scoping is lifted.
     *
     * @return array<string, \Closure>
     */
    private function relations(): array
    {
        $unscoped = fn ($q) => $q->withoutGlobalScopes();

        return [
            'tenant' => $unscoped,
            'tenant.subscription' => $unscoped,
            'tenant.branding' => $unscoped,
            'department' => $unscoped,
        ];
    }

    /**
     * @return Builder<Vacancy>
     */
    private function query(): Builder
    {
        return Vacancy::query()
            ->withoutGlobalScopes()
            ->where('status', VacancyStatus::Open->value)
            ->where(fn (Builder $q) => $q->whereNull('closes_on')->orWhereDate('closes_on', '>=', CarbonImmutable::today()->toDateString()))
            ->whereHas('tenant', fn (Builder $t) => $t->whereNotIn('status', [
                TenantStatus::Suspended->value, TenantStatus::Cancelled->value, TenantStatus::Expired->value,
            ]));
    }
}
