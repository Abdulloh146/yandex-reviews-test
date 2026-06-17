<?php

namespace App\Http\Controllers;

use App\Actions\ParseYandexOrganizationAction;
use App\Models\Organization;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $organization = Organization::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        return response()->json([
            'organization' => $organization,
        ]);
    }

    public function store(Request $request, ParseYandexOrganizationAction $action): JsonResponse
    {
        $validated = $request->validate([
            'yandex_url' => [
                'required',
                'string',
                'max:4096',
                'regex:/^https?:\/\/(yandex\.(ru|uz|com)|maps\.yandex\.)/i',
            ],
        ], [
            'yandex_url.required' => 'Yandex Maps link kiriting.',
            'yandex_url.regex' => 'Faqat Yandex Maps link kiriting.',
        ]);

        $organization = Organization::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
            ],
            [
                'yandex_url' => $validated['yandex_url'],
                'name' => null,
                'average_rating' => null,
                'ratings_count' => 0,
                'reviews_count' => 0,
                'parsed_reviews_count' => 0,
                'parse_status' => 'processing',
                'parse_error' => null,
                'last_parsed_at' => null,
            ]
        );

        Review::query()
            ->where('organization_id', $organization->id)
            ->delete();

        // DIRECT MODE: queue/job ishlatilmaydi. Request shu yerda parser tugaguncha kutadi.
        $organization = $action->handle($organization->fresh());

        if ($organization->parse_status !== 'success') {
            return response()->json([
                'message' => $organization->parse_error ?: 'Parsing failed.',
                'organization' => $organization,
            ], 422);
        }

        return response()->json([
            'message' => $organization->parse_error
                ? 'Organization parsed with warning.'
                : 'Organization found and reviews parsed successfully.',
            'organization' => $organization,
        ]);
    }

    public function refresh(Request $request, ParseYandexOrganizationAction $action): JsonResponse
    {
        $organization = Organization::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        if (! $organization) {
            return response()->json([
                'message' => 'Organization not found. First save Yandex Maps link.',
            ], 404);
        }

        Review::query()
            ->where('organization_id', $organization->id)
            ->delete();

        $organization->update([
            'name' => null,
            'average_rating' => null,
            'ratings_count' => 0,
            'reviews_count' => 0,
            'parsed_reviews_count' => 0,
            'parse_status' => 'processing',
            'parse_error' => null,
            'last_parsed_at' => null,
        ]);

        // DIRECT MODE: queue/job ishlatilmaydi. Request shu yerda parser tugaguncha kutadi.
        $organization = $action->handle($organization->fresh());

        if ($organization->parse_status !== 'success') {
            return response()->json([
                'message' => $organization->parse_error ?: 'Parsing failed.',
                'organization' => $organization,
            ], 422);
        }

        return response()->json([
            'message' => $organization->parse_error
                ? 'Organization refreshed with warning.'
                : 'Organization data refreshed successfully.',
            'organization' => $organization,
        ]);
    }

    public function reviews(Request $request): JsonResponse
    {
        $organization = Organization::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        if (! $organization) {
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 50,
                'total' => 0,
            ]);
        }

        $perPage = (int) $request->input('per_page', 50);
        $perPage = max(1, min($perPage, 50));

        $reviews = Review::query()
            ->where('organization_id', $organization->id)
            ->orderByRaw('review_date IS NULL ASC')
            ->orderByDesc('review_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($reviews);
    }
}
