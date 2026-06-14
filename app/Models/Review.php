<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'organization_id',
        'external_id',
        'author_name',
        'review_date',
        'review_date_text',
        'text',
        'rating',
        'content_hash',
    ];

    protected $casts = [
        'rating'      => 'float',
        'review_date' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
