<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Offer;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class OfferPolicy
{
    use AuthorizesWithinTenant;

    public function view(User $user, Offer $offer): bool
    {
        return $user->tenant_id === $offer->tenant_id
            && $this->permits($user, $offer->tenant_id, Permission::RecruitmentView, Permission::RecruitmentManage);
    }

    public function manage(User $user, Offer $offer): bool
    {
        return $user->tenant_id === $offer->tenant_id
            && $this->permits($user, $offer->tenant_id, Permission::RecruitmentManage);
    }
}
