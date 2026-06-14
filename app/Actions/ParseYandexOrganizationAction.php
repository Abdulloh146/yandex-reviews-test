<?php

namespace App\Actions;

use App\Models\Organization;
use App\Services\YandexMapsParserService;
use Illuminate\Support\Facades\DB;
use Throwable;

readonly class ParseYandexOrganizationAction
{
    public function __construct(
        private YandexMapsParserService $parser
    ) {}

    public function handle(Organization $organization): Organization
    {
        try {
            $organization->update([
                'parse_status' => 'pending',
                'parse_error' => null,
            ]);

            $data = $this->parser->parse($organization->yandex_url);

            return DB::transaction(function () use ($organization, $data) {
                $reviews = $data['reviews'] ?? [];

                $organization->update([
                    'name' => $data['name'] ?? null,
                    'average_rating' => $data['average_rating'] ?? null,
                    'ratings_count' => $data['ratings_count'] ?? 0,
                    'reviews_count' => $data['reviews_count'] ?? count($reviews),
                    'parse_status' => 'success',
                    'parse_error' => null,
                    'last_parsed_at' => now(),
                ]);

                $organization->reviews()->delete();

                foreach ($reviews as $review) {
                    $contentHash = sha1(
                        ($review['author_name'] ?? '') .
                        ($review['review_date'] ?? '') .
                        ($review['text'] ?? '') .
                        ($review['rating'] ?? '')
                    );

                    $organization->reviews()->create([
                        'external_id' => $review['external_id'] ?? $contentHash,
                        'author_name' => $review['author_name'] ?? null,
                        'review_date' => $review['review_date'] ?? null,
                        'review_date_text' => $review['review_date_text'] ?? null,
                        'text' => $review['text'] ?? null,
                        'rating' => $review['rating'] ?? null,
                        'content_hash' => $contentHash,
                    ]);
                }

                return $organization->fresh();
            });
        } catch (Throwable $e) {
            $organization->update([
                'parse_status' => 'failed',
                'parse_error' => $e->getMessage(),
                'last_parsed_at' => now(),
            ]);

            return $organization->fresh();
        }
    }
}
