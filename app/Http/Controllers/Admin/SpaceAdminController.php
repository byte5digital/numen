<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SpaceAdminController extends Controller
{
    /**
     * List all spaces, marking the current active space.
     */
    public function index(): Response
    {
        $currentSpace = app()->has('current_space') ? app('current_space') : null;

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
     */
    public function edit(Space $space): Response
    {
        return Inertia::render('Admin/Spaces/Edit', [
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
                'description' => $space->description,
                'default_locale' => $space->default_locale,
                'settings' => $space->settings,
                'api_config' => $space->api_config,
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

        $space->update($validated);

        return redirect()->route('admin.spaces.index')
            ->with('success', 'Space updated successfully.');
    }

    /**
     * Delete the specified space, blocking if it is the last one.
     */
    public function destroy(Space $space): RedirectResponse
    {
        if (Space::count() <= 1) {
            return redirect()->route('admin.spaces.index')
                ->with('error', 'Cannot delete the last remaining space.');
        }

        $space->delete();

        return redirect()->route('admin.spaces.index')
            ->with('success', 'Space deleted successfully.');
    }
}
