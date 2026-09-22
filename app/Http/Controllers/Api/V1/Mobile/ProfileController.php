<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\Mobile\IssueMobileToken;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user, their employee record, permissions and organisation —
 * the same payload the app receives at sign-in, refreshed on start-up.
 */
class ProfileController extends Controller
{
    public function __invoke(Request $request, IssueMobileToken $issuer): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return response()->json(['data' => $issuer->profile($user)]);
    }
}
