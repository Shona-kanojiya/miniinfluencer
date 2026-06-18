<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class InstagramService
{
    public function fetchProfile(string $username): array
    {
        $response = Http::timeout(15)
            ->connectTimeout(3)
            ->withHeaders([
                'x-rapidapi-key'  => config('services.rapidapi.key'),
                'x-rapidapi-host' => config('services.rapidapi.host'),
            ])
            ->get('https://instagram-scraper-api2.p.rapidapi.com/v1/info', [
                'username_or_id_or_url' => $username,
            ]);

        if ($response->status() === 404) {
            throw new \RuntimeException('FATAL:Profile not found', 404);
        }

        if ($response->status() === 401) {
            throw new \RuntimeException('FATAL:Unauthorized - check API key', 401);
        }

        $response->throw(); // throws on 5xx

        $data = $response->json('data');

        return [
            'followers_count'     => $data['follower_count'] ?? 0,
            'following_count'     => $data['following_count'] ?? 0,
            'post_count'          => $data['media_count'] ?? 0,
            'bio'                 => $data['biography'] ?? null,
            'profile_picture_url' => $data['profile_pic_url'] ?? null,
        ];
    }
}