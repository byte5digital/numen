<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Services\LocaleService;
use Inertia\Inertia;
use Inertia\Response;

class LocaleAdminController extends Controller
{
    public function __construct(private readonly LocaleService $localeService) {}

    public function index(): Response
    {
        $space = Space::first();
        $locales = $space ? $this->localeService->getLocalesForSpace($space) : collect();

        $supportedRaw = $this->localeService->getSupportedLocales();
        $supported = array_values(array_map(
            fn (string $code, string $label) => ['code' => $code, 'label' => $label],
            array_keys($supportedRaw),
            array_values($supportedRaw),
        ));

        return Inertia::render('Settings/Locales', [
            'locales' => $locales->values(),
            'supported' => $supported,
        ]);
    }
}
