<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    public function check()
    {
        $failing = [];

        try { DB::select('SELECT 1'); } catch (\Exception) { $failing[] = 'database'; }
        try { Redis::ping(); }         catch (\Exception) { $failing[] = 'redis'; }

        // Queue heartbeat — worker sets this key every job run
        if (!Cache::has('queue:heartbeat')) {
            $failing[] = 'queue';
        }

        if (empty($failing)) {
            return response()->json(['status' => 'ok']);
        }

        return response()->json(['status' => 'degraded', 'failing' => $failing], 503);
    }
}