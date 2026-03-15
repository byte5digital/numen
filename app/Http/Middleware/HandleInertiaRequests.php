<?php

namespace App\Http\Middleware;

use App\Models\Plugin;
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
        ];
    }
}
