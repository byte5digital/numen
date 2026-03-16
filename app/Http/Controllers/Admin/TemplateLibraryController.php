<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TemplateLibraryController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $space = Space::where('owner_id', $user->id)->first();

        return Inertia::render('Pipelines/Templates/Library', [
            'spaceId' => ($space !== null ? $space->id : ''),
        ]);
    }

    public function create(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $space = Space::where('owner_id', $user->id)->first();

        return Inertia::render('Pipelines/Templates/Editor', [
            'spaceId' => ($space !== null ? $space->id : ''),
        ]);
    }

    public function edit(Request $request, string $templateId)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $space = Space::where('owner_id', $user->id)->first();

        return Inertia::render('Pipelines/Templates/Editor', [
            'spaceId' => ($space !== null ? $space->id : ''),
            'templateId' => $templateId,
        ]);
    }
}
