<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentQualityConfig;
use App\Models\Space;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QualitySettingsController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Space|null $space */
        $space = app()->bound('current_space')
            ? app('current_space')
            : ($request->has('space_id') ? Space::find($request->input('space_id')) : null);

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
