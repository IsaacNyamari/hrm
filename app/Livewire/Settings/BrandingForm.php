<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Livewire\Concerns\BindsTenant;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class BrandingForm extends Component
{
    use BindsTenant;
    use WithFileUploads;

    public string $brand_name = '';

    public string $primary_color = '#4f46e5';

    public string $from_name = '';

    public string $from_email = '';

    public string $reply_to_email = '';

    public string $email_footer = '';

    public ?TemporaryUploadedFile $logo = null;

    public ?string $notice = null;

    public function mount(): void
    {
        $user = auth()->user();
        abort_if($user === null, 403);
        abort_unless($user->can('settings.manage'), 403);

        $tenant = $user->tenant;
        if ($tenant === null) {
            return;
        }

        $branding = $tenant->brandingOrNew();
        $this->brand_name = (string) $branding->brand_name;
        $this->primary_color = $branding->primary_color;
        $this->from_name = (string) $branding->from_name;
        $this->from_email = (string) $branding->from_email;
        $this->reply_to_email = (string) $branding->reply_to_email;
        $this->email_footer = (string) $branding->email_footer;
    }

    public function save(): void
    {
        $user = auth()->user();
        abort_if($user === null, 403);
        abort_unless($user->can('settings.manage'), 403);

        $data = $this->validate([
            'brand_name' => ['nullable', 'string', 'max:120'],
            'primary_color' => ['required', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'from_email' => ['nullable', 'email', 'max:150'],
            'reply_to_email' => ['nullable', 'email', 'max:150'],
            'email_footer' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $tenant = $user->tenant;
        if ($tenant === null) {
            return;
        }

        $branding = $tenant->brandingOrNew();
        $branding->fill([
            'brand_name' => $data['brand_name'] ?? null,
            'primary_color' => $data['primary_color'],
            'from_name' => $data['from_name'] ?? null,
            'from_email' => $data['from_email'] ?? null,
            'reply_to_email' => $data['reply_to_email'] ?? null,
            'email_footer' => $data['email_footer'] ?? null,
        ]);
        $branding->tenant_id = $tenant->getKey();

        if ($this->logo !== null) {
            $stored = $this->logo->store("branding/{$tenant->uuid}", 'public');
            if (is_string($stored)) {
                $branding->logo_disk = 'public';
                $branding->logo_path = $stored;
            }
        }

        $branding->save();
        $this->logo = null;
        $this->notice = 'Branding saved.';
    }

    public function render(): View
    {
        $tenant = auth()->user()?->tenant;
        $currentLogo = $tenant !== null ? $tenant->brandingOrNew()->logoUrl() : null;

        return view('livewire.settings.branding-form', [
            'currentLogo' => $currentLogo,
        ]);
    }
}
