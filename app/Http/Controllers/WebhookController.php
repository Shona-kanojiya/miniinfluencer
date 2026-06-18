<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class WebhookController extends Controller
{
    public function handle(Request $request, string $provider)
    {
        // 1. Verify HMAC signature
        $signature = $request->header('X-Webhook-Signature');
        $secret    = config('services.webhook_secret');
        $expected  = hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expected, $signature ?? '')) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // 2. Replay protection — reject duplicate nonces within 24h
        $nonce = $request->header('X-Webhook-Nonce') ?? md5($request->getContent());
        $key   = "webhook:nonce:{$nonce}";

        if (Redis::exists($key)) {
            return response()->json(['error' => 'Duplicate request'], 409);
        }

        Redis::setex($key, 86400, 1); // store for 24 hours

        // 3. Push real work to queue — return 200 immediately
        // dispatch(new ProcessWebhookJob($request->all()));

        return response()->json(['status' => 'accepted']);
    }
}