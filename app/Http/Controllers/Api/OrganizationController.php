<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = Organization::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
            ],
            [
                'yandex_url' => $request->validated('yandex_url'),
                'parse_status' => 'pending',
                'parse_error' => null,
            ]
        );

        return response()->json([
            'message' => 'Organization link saved successfully.',
            'organization' => $organization,
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $organization = Organization::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->firstOrFail();

        // Hozircha parser yo‘q, keyingi qadamda shu yerga service ulaymiz.
        $organization->update([
            'parse_status' => 'pending',
            'parse_error' => null,
        ]);

        return response()->json([
            'message' => 'Parsing will be implemented in next step.',
            'organization' => $organization,
        ]);
    }
}
