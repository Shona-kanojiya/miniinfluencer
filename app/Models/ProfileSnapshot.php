<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileSnapshot extends Model
{
    protected $fillable = [
        'profile_id', 'followers_count',
        'following_count', 'post_count', 'captured_at',
    ];

    protected $casts = [
        'captured_at'     => 'datetime',
        'followers_count' => 'integer',
        'following_count' => 'integer',
        'post_count'      => 'integer',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}