<?php

namespace App\Http\Controllers;

use App\Services\Settings;
use Illuminate\Http\JsonResponse;

class SystemController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json(['status' => 'success', 'uptime' => microtime(true) - LARAVEL_START]);
    }

    public function config(): JsonResponse
    {
        $all = Settings::all();

        return response()->json([
            'status' => 'success',
            'data' => [
                'brand_name' => $all['brand_name'] ?? config('portal.brand_name'),
                'default_rate' => (float) ($all['default_rate'] ?? config('portal.default_rate')),
                'signup_bonus' => (int) ($all['signup_bonus'] ?? config('portal.signup_bonus')),
                'support_email' => $all['support_email'] ?? config('portal.support_email'),
                'currency' => $all['currency'] ?? config('portal.currency'),
            ],
        ]);
    }
}
