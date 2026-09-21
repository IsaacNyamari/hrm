<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\View\View;

class PricingController extends Controller
{
    public function __invoke(): View
    {
        return view('marketing.pricing', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('price_cents')->get(),
        ]);
    }
}
