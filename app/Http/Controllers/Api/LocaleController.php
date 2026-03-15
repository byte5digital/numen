<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateLocaleRequest;
use App\Http\Requests\UpdateLocaleRequest;
use App\Http\Resources\SpaceLocaleResource;
use App\Models\Space;
use App\Models\SpaceLocale;
use App\Services\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __construct(private readonly LocaleService $localeService) {}

    /**
     * List all space locales.
     *
     * GET /v1/locales?space_id=<required>
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string', 'exists:spaces,id'],
        ]);

        $space = Space::findOrFail($validated['space_id']);
        $locales = $this->localeService->getLocalesForSpace($space);

        return response()->json(['data' => SpaceLocaleResource::collection($locales)]);
    }

    /**
     * Add a locale to a space.
     *
     * POST /v1/locales
     */
    public function store(CreateLocaleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $space = Space::findOrFail($validated['space_id']);

        try {
            $spaceLocale = $this->localeService->addLocale(
                space: $space,
                locale: $validated['locale'],
                label: $validated['label'],
                isDefault: $validated['is_default'] ?? false,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Validation Error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new SpaceLocaleResource($spaceLocale)], 201);
    }

    /**
     * Update a space locale.
     *
     * PATCH /v1/locales/{locale}
     */
    public function update(UpdateLocaleRequest $request, SpaceLocale $locale): JsonResponse
    {
        $validated = $request->validated();

        $locale->update($validated);

        return response()->json(['data' => new SpaceLocaleResource($locale->fresh())]);
    }

    /**
     * Remove a locale from a space.
     *
     * DELETE /v1/locales/{locale}
     */
    public function destroy(SpaceLocale $locale): JsonResponse
    {
        $space = Space::findOrFail($locale->space_id);

        try {
            $this->localeService->removeLocale($space, $locale->locale);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'Conflict', 'message' => $e->getMessage()], 409);
        }

        return response()->json(null, 204);
    }

    /**
     * Set a locale as the default for its space.
     *
     * POST /v1/locales/{locale}/set-default
     */
    public function setDefault(Request $request, SpaceLocale $locale): JsonResponse
    {
        $space = Space::findOrFail($locale->space_id);

        try {
            $this->localeService->setDefaultLocale($space, $locale->locale);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'Conflict', 'message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => new SpaceLocaleResource($locale->fresh())]);
    }

    /**
     * Return the full list of supported IETF locales.
     *
     * GET /v1/locales/supported (no auth required)
     */
    public function supported(Request $request): JsonResponse
    {
        $locales = $this->localeService->getSupportedLocales();

        $data = array_map(
            fn (string $code, string $label) => ['locale' => $code, 'label' => $label],
            array_keys($locales),
            array_values($locales),
        );

        return response()->json(['data' => array_values($data)]);
    }
}
