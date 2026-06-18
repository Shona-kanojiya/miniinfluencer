<?php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class CircuitBreaker
{
    private string $failKey   = 'circuit:failures';
    private string $openedKey = 'circuit:opened_at';
    private int    $threshold = 10;       // failures before opening
    private int    $cooldown  = 120;      // seconds to stay open

    public function isOpen(): bool
    {
        $openedAt = Redis::get($this->openedKey);
        if (!$openedAt) return false;

        // Still within cooldown?
        if (now()->timestamp - (int)$openedAt < $this->cooldown) {
            return true;
        }

        // Cooldown passed — allow one test job through (HALF-OPEN)
        return false;
    }

    public function recordSuccess(): void
    {
        Redis::del($this->failKey);
        Redis::del($this->openedKey);
    }

    public function recordFailure(): void
    {
        $failures = Redis::incr($this->failKey);
        if ($failures >= $this->threshold) {
            Redis::set($this->openedKey, now()->timestamp);
            \Log::error('Circuit breaker opened', ['failures' => $failures]);
        }
    }
}