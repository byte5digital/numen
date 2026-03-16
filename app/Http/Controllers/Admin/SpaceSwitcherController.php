<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpaceSwitcherController extends Controller
{
    /**
     * Switch the active space for the current session.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string', 'exists:spaces,id'],
        ]);

        session(['current_space_id' => $validated['space_id']]);

        return redirect()->back();
    }
}
