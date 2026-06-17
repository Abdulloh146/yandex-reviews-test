<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'user_id',
        'yandex_url',
        'name',
        'average_rating',
        'ratings_count',
        'reviews_count',
        'parsed_reviews_count',
        'parse_status',
        'parse_error',
        'last_parsed_at',
    ];

    protected $casts = [
        'average_rating' => 'float',
        'ratings_count' => 'integer',
        'reviews_count' => 'integer',
        'parsed_reviews_count' => 'integer',
        'last_parsed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
