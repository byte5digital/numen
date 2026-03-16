<?php

namespace App\Http\Controllers\Api\Templates;

use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\RateTemplateRequest;
use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateRating;
use App\Models\Space;
use Illuminate\Http\JsonResponse;

class PipelineTemplateRatingController extends Controller
{
    public function index(Space $space, PipelineTemplate $template): JsonResponse
    {
        $ratings = $template->ratings()->with('user')->latest()->get();
        $average = $ratings->avg('rating');

        return response()->json([
            'data' => $ratings->map(fn (PipelineTemplateRating $r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'review' => $r->review,
                'user' => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name] : null,
                'created_at' => $r->created_at->toIso8601String(),
            ]),
            'meta' => [
                'average_rating' => $average ? round((float) $average, 2) : null,
                'total' => $ratings->count(),
            ],
        ]);
    }

    public function store(Space $space, PipelineTemplate $template, RateTemplateRequest $request): JsonResponse
    {
        $rating = PipelineTemplateRating::updateOrCreate(
            ['template_id' => $template->id, 'user_id' => $request->user()?->id],
            ['rating' => $request->integer('rating'), 'review' => $request->string('review')->value() ?: null],
        );

        return response()->json([
            'data' => ['id' => $rating->id, 'rating' => $rating->rating, 'review' => $rating->review, 'created_at' => $rating->created_at->toIso8601String()],
        ], 201);
    }
}
