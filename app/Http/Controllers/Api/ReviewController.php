<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = Organization::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->firstOrFail();

        $perPage = (int) $request->query('per_page', 50);

        if ($perPage < 1 || $perPage > 50) {
            $perPage = 50;
        }

        $reviews = $organization->reviews()
            ->latest('review_date')
            ->paginate($perPage);

        return response()->json($reviews);
    }
}
