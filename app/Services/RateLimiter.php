<?php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class RateLimiter
{
    private int $maxTokens  = 100;
    private int $refillRate = 10; // per minute

    private function bucketKey(): string
    {
        return 'quota:tokens';
    }

    public function consume(): bool
    {
        $key    = $this->bucketKey();
        $tokens = Redis::get($key);

        if ($tokens === null) {
            // First use — fill bucket
            Redis::set($key, $this->maxTokens - 1);
            Redis::expire($key, 3600);
            return true;
        }

        if ((int)$tokens <= 0) {
            \Log::warning('Rate limit bucket empty — deferring job');
            return false;
        }

        Redis::decr($key);
        return true;
    }
}