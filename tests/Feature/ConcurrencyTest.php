<?php
use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use Illuminate\Support\Facades\Http;

test('only one HTTP call made when two jobs run for same profile', function () {
    Http::fake(['*' => Http::response([
        'data' => [
            'follower_count'  => 1000,
            'following_count' => 500,
            'media_count'     => 200,
            'biography'       => 'Test bio',
            'profile_pic_url' => 'https://example.com/pic.jpg',
        ]
    ], 200)]);

    $profile = Profile::factory()->create(['status' => 'pending']);

    // Run two jobs synchronously — second should see lock and skip
    FetchProfileJob::dispatchSync($profile->id);
    FetchProfileJob::dispatchSync($profile->id);

    Http::assertSentCount(1);
});