<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Actions\ParseYandexOrganizationAction;

class OrganizationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $organization = Organization::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();

        return response()->json([
            'organization' => $organization,
        ]);
    }

    public function store(
        StoreOrganizationRequest $request,
        ParseYandexOrganizationAction $action
    ): JsonResponse {
        $organization = Organization::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
            ],
            [
                'yandex_url' => $request->validated('yandex_url'),
                'name' => null,
                'average_rating' => null,
                'ratings_count' => 0,
                'reviews_count' => 0,
                'parse_status' => 'pending',
                'parse_error' => null,
                'last_parsed_at' => null,
            ]
        );

        $organization->reviews()->delete();

        $organization = $action->handle($organization);

        return response()->json([
            'message' => $organization->parse_status === 'success'
                ? 'Organization link saved and data parsed successfully.'
                : 'Organization link saved, but parsing failed.',
            'organization' => $organization,
        ]);
    }

    public function refresh(Request $request, ParseYandexOrganizationAction $action): JsonResponse
    {
        $organization = Organization::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->firstOrFail();

        $organization = $action->handle($organization);

        return response()->json([
            'message' => $organization->parse_status === 'success'
                ? 'Organization data parsed successfully.'
                : 'Parsing failed.',
            'organization' => $organization,
        ]);
    }
}
