<?php

namespace App\Actions;

use App\Models\Organization;
use App\Models\Review;
use App\Services\YandexMapsParserService;
use Illuminate\Support\Facades\DB;
use Throwable;

readonly class ParseYandexOrganizationAction
{
    public function __construct(
        private YandexMapsParserService $parserService
    ) {}

    public function handle(Organization $organization): Organization
    {
        try {
            $data = $this->parserService->parse($organization->yandex_url);

            $reviews = collect($data['reviews'] ?? [])
                ->filter(fn ($review) => ! empty($review['external_id']) && ! empty($review['text']))
                ->unique(fn ($review) => ($review['author_name'] ?? '') . '|' . ($review['review_date_text'] ?? '') . '|' . ($review['text'] ?? ''))
                ->values()
                ->all();

            $yandexReviewsCount = (int) ($data['reviews_count'] ?? count($reviews));

            DB::transaction(function () use ($organization, $data, $reviews, $yandexReviewsCount) {
                Review::query()
                    ->where('organization_id', $organization->id)
                    ->delete();

                foreach ($reviews as $review) {
                    $contentHash = $review['content_hash']
                        ?? sha1(($review['author_name'] ?? '') . '|' . ($review['review_date_text'] ?? '') . '|' . ($review['text'] ?? ''));

                    Review::query()->create([
                        'organization_id' => $organization->id,
                        'external_id' => $review['external_id'],
                        'author_name' => $review['author_name'] ?? null,
                        'review_date' => $review['review_date'] ?? null,
                        'review_date_text' => $review['review_date_text'] ?? null,
                        'text' => $review['text'] ?? null,
                        'rating' => $review['rating'] ?? null,
                        'content_hash' => $contentHash,
                    ]);
                }

                $savedReviewsCount = Review::query()
                    ->where('organization_id', $organization->id)
                    ->count();

                $organization->update([
                    'name' => $data['name'] ?? $organization->name,
                    'average_rating' => $data['average_rating'] ?? null,
                    'ratings_count' => (int) ($data['ratings_count'] ?? 0),
                    'reviews_count' => $yandexReviewsCount,
                    'parsed_reviews_count' => $savedReviewsCount,
                    'parse_status' => 'success',
                    'parse_error' => $data['parse_warning'] ?? null,
                    'last_parsed_at' => now(),
                ]);
            });

            return $organization->fresh();
        } catch (Throwable $exception) {
            $organization->update([
                'parse_status' => 'failed',
                'parse_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);

            return $organization->fresh();
        }
    }
}
