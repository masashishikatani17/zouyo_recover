<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

final class StatusController extends Controller
{
    /**
     * 単一アクション（/status）
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app'   => fn () => ['env' => app()->environment(), 'debug' => config('app.debug')],
            'db'    => function () {
                // SELECT 1 でDB疎通を最小確認
                DB::select('SELECT 1');
                return ['connection' => Config::get('database.default')];
            },
            'cache' => fn () => ['driver' => Config::get('cache.default')],
            'queue' => fn () => ['connection' => Config::get('queue.default')],
        ];

        $result = ['ok' => true, 'checks' => [], 'timestamp' => now()->toIso8601String()];

        foreach ($checks as $name => $fn) {
            try {
                $result['checks'][$name] = ['ok' => true, 'info' => $fn()];
            } catch (Throwable $e) {
                $result['ok'] = false;
                $result['checks'][$name] = [
                    'ok' => false,
                    'error' => class_basename($e) . ': ' . $e->getMessage(),
                ];
            }
        }

        return response()->json($result, $result['ok'] ? 200 : 500, [], JSON_UNESCAPED_UNICODE);
    }
}