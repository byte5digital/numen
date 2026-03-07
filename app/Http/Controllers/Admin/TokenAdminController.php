<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TokenAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $tokens = $request->user()
            ->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at->toIso8601String(),
            ]);

        return Inertia::render('Settings/Tokens', [
            'tokens' => $tokens,
            'newToken' => session('newToken'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $token = $request->user()->createToken($request->name);

        return redirect()
            ->route('admin.tokens.index')
            ->with('newToken', $token->plainTextToken);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->tokens()->where('id', $id)->delete();

        return redirect()->route('admin.tokens.index');
    }
}
