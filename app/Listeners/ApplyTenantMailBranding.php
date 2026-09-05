<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\TenantBranding;
use App\Support\TenantContext;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Rewrites the From / Reply-To of every outgoing mail to the bound tenant's
 * branding when one is present. Falls back to the platform defaults for
 * platform / unauthenticated mail.
 */
class ApplyTenantMailBranding
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(MessageSending $event): void
    {
        $tenant = $this->context->get();

        if ($tenant === null) {
            return;
        }

        $branding = $tenant->relationLoaded('branding')
            ? $tenant->getRelation('branding')
            : $tenant->branding()->first();

        $fromEmail = ($branding instanceof TenantBranding ? $branding->from_email : null)
            ?? config('mail.from.address');
        $fromName = $branding instanceof TenantBranding
            ? $branding->fromName()
            : ($tenant->name ?? config('mail.from.name'));

        if (is_string($fromEmail) && $fromEmail !== '') {
            $event->message->from(new Address($fromEmail, (string) $fromName));
        }

        $replyTo = $branding instanceof TenantBranding ? $branding->reply_to_email : null;
        if (is_string($replyTo) && $replyTo !== '') {
            $event->message->replyTo($replyTo);
        }
    }
}
