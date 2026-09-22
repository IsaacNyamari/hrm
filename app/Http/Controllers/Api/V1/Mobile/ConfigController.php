<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public start-up configuration for the mobile app, so nothing but the API
 * base URL is baked into the build. The Reverb app key is public by design.
 */
class ConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'app_name' => config('app.name'),
            'reverb' => [
                'key' => config('mobile.reverb.key'),
                'host' => config('mobile.reverb.host'),
                'port' => (int) config('mobile.reverb.port'),
                'scheme' => config('mobile.reverb.scheme'),
            ],
            'broadcasting_auth_url' => url('/api/broadcasting/auth'),
            'min_app_version' => config('mobile.min_version'),
            'latest_app_version' => config('mobile.latest_version'),
            'store_urls' => config('mobile.store_urls'),
            'support' => config('mobile.support'),
            'privacy_url' => config('mobile.privacy_url'),
            'web_url' => rtrim((string) config('app.url'), '/'),
        ]])->header('Cache-Control', 'public, max-age=300');
    }
}
