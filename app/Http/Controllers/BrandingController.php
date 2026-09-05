<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\TenantBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('settings.manage'), 403);

        return response()->json(['data' => $this->transform(Tenancy::getOrFail()->brandingOrNew())]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('settings.manage'), 403);

        $data = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:120'],
            'primary_color' => ['sometimes', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'from_email' => ['nullable', 'email', 'max:150'],
            'reply_to_email' => ['nullable', 'email', 'max:150'],
            'support_email' => ['nullable', 'email', 'max:150'],
            'email_footer' => ['nullable', 'string', 'max:2000'],
            'email_signature' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ]);

        $tenant = Tenancy::getOrFail();
        $branding = $tenant->brandingOrNew();
        $branding->fill(Arr::except($data, ['logo']));
        $branding->tenant_id = $tenant->getKey();

        if ($request->hasFile('logo')) {
            if ($branding->logo_path !== null) {
                Storage::disk($branding->logo_disk ?? 'public')->delete($branding->logo_path);
            }

            $stored = $request->file('logo')->store("branding/{$tenant->uuid}", 'public');

            if ($stored !== false) {
                $branding->logo_disk = 'public';
                $branding->logo_path = $stored;
            }
        }

        $branding->save();

        return response()->json(['data' => $this->transform($branding->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(TenantBranding $branding): array
    {
        return [
            'brand_name' => $branding->brand_name,
            'display_name' => $branding->displayName(),
            'logo_url' => $branding->logoUrl(),
            'primary_color' => $branding->primary_color,
            'accent_color' => $branding->accent_color,
            'from_name' => $branding->from_name,
            'from_email' => $branding->from_email,
            'reply_to_email' => $branding->reply_to_email,
            'support_email' => $branding->support_email,
            'email_footer' => $branding->email_footer,
            'email_signature' => $branding->email_signature,
        ];
    }
}
