<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentTranslationJob;
use App\Models\Space;
use App\Services\LocaleService;
use Inertia\Inertia;
use Inertia\Response;

class TranslationAdminController extends Controller
{
    public function __construct(private readonly LocaleService $localeService) {}

    public function show(string $content): Response
    {
        $contentModel = Content::with(['currentVersion', 'contentType'])->findOrFail($content);

        $space = Space::findOrFail($contentModel->space_id);
        $locales = $this->localeService->getLocalesForSpace($space);

        $jobs = ContentTranslationJob::where('source_content_id', $contentModel->id)
            ->orderByDesc('created_at')
            ->get();

        $translations = $jobs->keyBy('target_locale')->map(fn ($job) => [
            'id' => $job->id,
            'status' => $job->status,
            'target_locale' => $job->target_locale,
            'target_content_id' => $job->target_content_id,
            'error_message' => $job->error_message,
            'completed_at' => $job->completed_at?->toIso8601String(),
        ])->toArray();

        return Inertia::render('Content/Translations', [
            'content' => [
                'id' => $contentModel->id,
                'title' => $contentModel->currentVersion->title ?? $contentModel->slug,
                'locale' => $contentModel->locale,
                'source_locale' => $contentModel->locale,
            ],
            'locales' => $locales->map(fn ($l) => [
                'code' => $l->locale,
                'label' => $l->label,
                'is_default' => (bool) $l->is_default,
            ])->values(),
            'translations' => $translations,
        ]);
    }
}
