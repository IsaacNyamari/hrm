<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Enums\BillingInterval;
use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->canOnPlatform(PlatformPermission::PlansManage), 403);

        return view('platform.plans.index', [
            'plans' => Plan::query()->withCount('subscriptions')->orderBy('sort_order')->orderBy('price_cents')->get(),
            'intervals' => BillingInterval::cases(),
        ]);
    }

    public function store(Request $request, Auditor $auditor): RedirectResponse
    {
        abort_unless($request->user()?->canOnPlatform(PlatformPermission::PlansManage), 403);

        $data = $this->validated($request);
        $data['features'] ??= ['flags' => [], 'limits' => []];

        $plan = Plan::query()->create($data);
        $auditor->log('platform.plan.created', $plan, ['slug' => $plan->slug]);

        return redirect()->route('platform.plans.index')->with('status', __('Plan “:name” created.', ['name' => $plan->name]));
    }

    public function update(Request $request, Plan $plan, Auditor $auditor): RedirectResponse
    {
        abort_unless($request->user()?->canOnPlatform(PlatformPermission::PlansManage), 403);

        $plan->update($this->validated($request, $plan));
        $auditor->log('platform.plan.updated', $plan, ['slug' => $plan->slug]);

        return redirect()->route('platform.plans.index')->with('status', __('Plan “:name” updated.', ['name' => $plan->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Plan $plan = null): array
    {
        $required = $plan === null ? 'required' : 'sometimes';

        $data = $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_cents' => [$required, 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'billing_interval' => [$required, new Enum(BillingInterval::class)],
            'employee_limit' => ['nullable', 'integer', 'min:1'],
            'storage_limit_mb' => ['nullable', 'integer', 'min:1'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($plan === null && isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(4));
        }

        return $data;
    }
}
