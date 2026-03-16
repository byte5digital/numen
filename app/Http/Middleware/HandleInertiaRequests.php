<?php

namespace App\Http\Middleware;

use App\Models\Plugin;
use App\Models\Space;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'plugins' => [
                'components' => fn () => Plugin::where('status', 'active')
                    ->get(['name', 'display_name', 'status', 'manifest'])
                    ->map(fn ($p) => [
                        'name' => $p->name,
                        'display_name' => $p->display_name,
                        'status' => $p->status,
                        'components' => $p->manifest['components'] ?? [],
                    ])
                    ->values()
                    ->toArray(),
            ],
            'currentSpace' => fn () => $request->attributes->has('space') && $request->attributes->get('space')
                ? [
                    'id' => $request->attributes->get('space')->id,
                    'name' => $request->attributes->get('space')->name,
                    'slug' => $request->attributes->get('space')->slug,
                ]
                : null,
            'spaces' => fn () => $request->user()
                ? Space::all()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug])->toArray()
                : [],
        ];
    }
}
