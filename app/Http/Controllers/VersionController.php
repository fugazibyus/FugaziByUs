<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Which commit this server runs and when it was deployed. The Deploy Gate checks this on
 * staging before it ships a release to production.
 */
class VersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'sha' => config('app.version'),
            'deployed_at' => config('app.deployed_at'),
        ])->header('Cache-Control', 'no-store');
    }
}
