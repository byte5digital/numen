<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LocaleService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocaleAdminController extends Controller
{
    public function __construct(private readonly LocaleService $localeService) {}

    public function index(Request $request): Response
    {
        $space = $request->space();
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
