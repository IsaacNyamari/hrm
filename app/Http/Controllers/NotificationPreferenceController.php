<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationCategory;
use App\Facades\Tenancy;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $stored = NotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->get()
            ->keyBy('category');

        $data = collect(NotificationCategory::userConfigurable())
            ->map(fn (NotificationCategory $category): array => [
                'category' => $category->value,
                'label' => $category->label(),
                'mail' => ($stored->get($category->value)?->channels['mail'] ?? true) === true,
                'database' => true,
            ])
            ->values();

        return response()->json(['data' => $data]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $configurable = array_map(
            static fn (NotificationCategory $c): string => $c->value,
            NotificationCategory::userConfigurable(),
        );

        $data = $request->validate([
            'category' => ['required', Rule::in($configurable)],
            'mail' => ['required', 'boolean'],
        ]);

        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->getKey(), 'category' => $data['category']],
            ['tenant_id' => Tenancy::id(), 'channels' => ['mail' => (bool) $data['mail']]],
        );

        return $this->show($request);
    }
}
