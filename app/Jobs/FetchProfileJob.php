<?php
namespace App\Jobs;

use App\Models\Profile;
use App\Models\ProfileSnapshot;
use App\Services\CircuitBreaker;
use App\Services\InstagramService;
use App\Services\RateLimiter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchProfileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $timeout = 60;

    public function __construct(public readonly int $profileId) {}

    public function handle(
        InstagramService $instagram,
        CircuitBreaker   $circuit,
        RateLimiter      $limiter,
    ): void {
        $start = now();

        // 1. Circuit breaker check
        if ($circuit->isOpen()) {
            Log::warning('Circuit open — re-deferring job', ['profile_id' => $this->profileId]);
            self::dispatch($this->profileId)->delay(now()->addMinutes(2));
            return;
        }

        // 2. Concurrency lock — skip if another worker has this profile
        $acquired = DB::transaction(function () {
            $profile = Profile::whereId($this->profileId)
                ->lockForUpdate()
                ->first();

            if (!$profile || $profile->status === 'fetching') {
                return false; // another worker has it
            }

            $profile->update(['status' => 'fetching']);
            return true;
        });

        if (!$acquired) {
            Log::info('Lock not acquired — skipping', ['profile_id' => $this->profileId]);
            return;
        }

        // 3. Rate limit check
        if (!$limiter->consume()) {
            Profile::whereId($this->profileId)->update(['status' => 'pending']);
            self::dispatch($this->profileId)->delay(now()->addMinutes(
                $this->exponentialDelay()
            ));
            return;
        }

        // 4. Fetch from API
        try {
            $data = $instagram->fetchProfile(
                Profile::find($this->profileId)->username
            );

            // 5. Write snapshot + update profile in one transaction
            DB::transaction(function () use ($data) {
                ProfileSnapshot::create([
                    'profile_id'      => $this->profileId,
                    'followers_count' => $data['followers_count'],
                    'following_count' => $data['following_count'],
                    'post_count'      => $data['post_count'],
                    'captured_at'     => now(),
                ]);

                Profile::whereId($this->profileId)->update([
                    ...$data,
                    'status'            => 'fetched',
                    'last_refreshed_at' => now(),
                    'last_error'        => null,
                ]);
            });

            $circuit->recordSuccess();

            Log::info('', [
                'job_id'      => $this->job->getJobId(),
                'profile_id'  => $this->profileId,
                'attempt'     => $this->attempts(),
                'duration_ms' => now()->diffInMilliseconds($start),
                'outcome'     => 'success',
            ]);

        } catch (\RuntimeException $e) {
            // Fatal errors — 404 or 401
            if (str_starts_with($e->getMessage(), 'FATAL:')) {
                Profile::whereId($this->profileId)->update([
                    'status'     => 'failed',
                    'last_error' => $e->getMessage(),
                ]);
                $this->fail($e); // no retry
                return;
            }
            $this->handleRetriableFailure($e, $circuit, $start);
        } catch (\Exception $e) {
            $this->handleRetriableFailure($e, $circuit, $start);
        }
    }

    private function handleRetriableFailure(\Throwable $e, CircuitBreaker $circuit, $start): void
    {
        $circuit->recordFailure();

        Profile::whereId($this->profileId)->update([
            'status'     => 'pending',
            'last_error' => $e->getMessage(),
        ]);

        Log::warning('', [
            'job_id'      => $this->job->getJobId(),
            'profile_id'  => $this->profileId,
            'attempt'     => $this->attempts(),
            'duration_ms' => now()->diffInMilliseconds($start),
            'outcome'     => 'retriable_failure',
            'error'       => $e->getMessage(),
        ]);

        $this->release($this->exponentialDelay() * 60);
    }

    private function exponentialDelay(): int
    {
        return min(pow(2, $this->attempts() - 1), 32); // 1, 2, 4, 8, 16, 32 mins
    }
}