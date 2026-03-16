<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SpaceAdminController extends Controller
{
    /**
     * List all spaces, marking the current active space.
     */
    public function index(Request $request): Response
    {
        $currentSpace = $request->attributes->get('space');

        $spaces = Space::orderBy('created_at', 'asc')
            ->get()
            ->map(fn (Space $space) => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
                'description' => $space->description,
                'default_locale' => $space->default_locale,
                'created_at' => $space->created_at->toIso8601String(),
                'is_current' => $currentSpace && $currentSpace->id === $space->id,
            ]);

        return Inertia::render('Admin/Spaces/Index', [
            'spaces' => $spaces,
        ]);
    }

    /**
     * Show the form for creating a new space.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Spaces/Create');
    }

    /**
     * Store a newly created space.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('spaces', 'slug')],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_locale' => ['required', 'string', 'max:10'],
        ]);

        Space::create($validated);

        return redirect()->route('admin.spaces.index')
            ->with('success', 'Space created successfully.');
    }

    /**
     * Show the form for editing the specified space.
     *
     * api_config values are masked (keys preserved, values replaced with '***')
     * to prevent sensitive credentials from being exposed to the browser.
     */
    public function edit(Space $space): Response
    {
        $apiConfig = $space->api_config;
        $maskedApiConfig = null;

        if (is_array($apiConfig)) {
            $maskedApiConfig = array_map(fn () => '***', $apiConfig);
        }

        return Inertia::render('Admin/Spaces/Edit', [
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
                'description' => $space->description,
                'default_locale' => $space->default_locale,
                'settings' => $space->settings,
                'api_config' => $maskedApiConfig,
            ],
        ]);
    }

    /**
     * Update the specified space.
     */
    public function update(Request $request, Space $space): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('spaces', 'slug')->ignore($space->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_locale' => ['required', 'string', 'max:10'],
            'settings' => ['nullable', 'array'],
            'api_config' => ['nullable', 'array'],
        ]);

        // If api_config was submitted as all-masked values (all '***'), don't overwrite stored secrets
        if (isset($validated['api_config']) && is_array($validated['api_config'])) {
            $allMasked = collect($validated['api_config'])->every(fn ($v) => $v === '***');
            if ($allMasked) {
                unset($validated['api_config']);
            }
        }

        $space->update($validated);

        return redirect()->route('admin.spaces.index')
            ->with('success', 'Space updated successfully.');
    }

    /**
     * Delete the specified space, blocking if it is the last one.
     * Uses a database transaction with a row lock to prevent TOCTOU race conditions.
     */
    public function destroy(string $id): RedirectResponse
    {
        $earlyReturn = null;

        DB::transaction(function () use ($id, &$earlyReturn): void {
            $space = Space::lockForUpdate()->findOrFail($id);

            if (Space::count() <= 1) {
                $earlyReturn = redirect()->route('admin.spaces.index')
                    ->with('error', 'Cannot delete the last space.');

                return;
            }

            $space->delete();
        });

        return $earlyReturn ?? redirect()->route('admin.spaces.index')
            ->with('success', 'Space deleted successfully.');
    }
}
