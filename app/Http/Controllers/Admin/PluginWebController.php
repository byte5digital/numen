<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plugin;
use Inertia\Inertia;
use Inertia\Response;

class PluginWebController extends Controller
{
    /**
     * GET /admin/plugins
     */
    public function index(): Response
    {
        $plugins = Plugin::withTrashed()
            ->with('settings')
            ->orderBy('display_name')
            ->get();

        return Inertia::render('Admin/Plugins/Index', [
            'plugins' => $plugins->map(fn ($p) => [
                'name' => $p->name,
                'display_name' => $p->display_name,
                'description' => $p->description,
                'version' => $p->version,
                'status' => $p->status,
                'manifest' => $p->manifest,
                'settings' => $p->settings->map(fn ($s) => [
                    'key' => $s->key,
                    'value' => $s->is_secret ? null : $s->value,
                ])->values(),
            ])->values(),
        ]);
    }

    /**
     * GET /admin/plugins/{name}
     */
    public function show(string $name): Response
    {
        $plugin = Plugin::withTrashed()
            ->with('settings')
            ->where('name', $name)
            ->firstOrFail();

        return Inertia::render('Admin/Plugins/Show', [
            'plugin' => [
                'name' => $plugin->name,
                'display_name' => $plugin->display_name,
                'description' => $plugin->description,
                'version' => $plugin->version,
                'status' => $plugin->status,
                'manifest' => $plugin->manifest,
                'settings' => $plugin->settings->map(fn ($s) => [
                    'key' => $s->key,
                    'value' => $s->is_secret ? null : $s->value,
                    'is_secret' => (bool) $s->is_secret,
                ])->values(),
            ],
        ]);
    }
}
