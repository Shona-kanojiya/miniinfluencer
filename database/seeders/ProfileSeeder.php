<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 0; $i < 1000; $i++) {
            $profile = \App\Models\Profile::create([
                'username'          => 'user_' . $i,
                'status'            => collect(['pending','fetched','failed'])->random(),
                'followers_count'   => rand(100, 1000000),
                'last_refreshed_at' => now()->subMinutes(rand(0, 200)),
            ]);

            for ($j = 0; $j < 10; $j++) {
                \App\Models\ProfileSnapshot::create([
                    'profile_id'      => $profile->id,
                    'followers_count' => rand(100, 1000000),
                    'following_count' => rand(100, 10000),
                    'post_count'      => rand(1, 500),
                    'captured_at'     => now()->subDays($j),
                ]);
            }
        }
    }
}
