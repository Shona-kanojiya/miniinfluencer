<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    protected $fillable = [
        'username', 'status', 'bio', 'profile_picture_url',
        'followers_count', 'following_count', 'post_count',
        'last_error', 'last_refreshed_at',
    ];

    protected $casts = [
        'last_refreshed_at' => 'datetime',
        'followers_count'   => 'integer',
        'following_count'   => 'integer',
        'post_count'        => 'integer',
    ];

    public function snapshots(): HasMany
    {
        return $this->hasMany(ProfileSnapshot::class)
                    ->orderByDesc('captured_at');
    }

    public function latestSnapshot(): HasMany
    {
        return $this->hasMany(ProfileSnapshot::class)
                    ->orderByDesc('captured_at')
                    ->limit(1);
    }
}