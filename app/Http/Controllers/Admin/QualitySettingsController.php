<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentQualityConfig;
use App\Models\Space;
use Inertia\Inertia;
use Inertia\Response;

class QualitySettingsController extends Controller
{
    public function index(): Response
    {
        /** @var Space|null $space */
        $space = Space::first();

        $config = $space !== null
            ? ContentQualityConfig::where('space_id', $space->id)->first()
            : null;

        return Inertia::render('Settings/Quality', [
            'spaceId' => $space !== null ? $space->id : '',
            'spaceName' => $space !== null ? $space->name : '',
            'config' => $config,
        ]);
    }
}
