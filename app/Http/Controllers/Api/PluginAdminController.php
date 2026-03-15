<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PluginResource;
use App\Models\Plugin;
use App\Plugin\PluginManager;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;
use Throwable;

class PluginAdminController extends Controller
{
    public function __construct(
        private readonly PluginManager $manager,
        private readonly AuthorizationService $authz,
    ) {}

    /**
     * GET /api/v1/admin/plugins
     * List all plugins (discovered + installed + active).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authz->authorize($request->user(), 'plugins.manage');

        $plugins = Plugin::withTrashed()
            ->with('settings')
            ->orderBy('display_name')
            ->get();

        return PluginResource::collection($plugins);
    }

    /**
     * GET /api/v1/admin/plugins/{name}
     * Plugin details + settings.
     */
    public function show(Request $request, string $name): PluginResource
    {
        $this->authz->authorize($request->user(), 'plugins.manage');

        $plugin = Plugin::withTrashed()
            ->with('settings')
            ->where('name', $name)
            ->firstOrFail();

        return new PluginResource($plugin);
    }

    /**
     * POST /api/v1/admin/plugins/{name}/install
     */
    public function install(Request $request, string $name): JsonResponse
    {
        $this->authz->authorize($request->user(), 'plugins.manage');

        try {
            $plugin = $this->manager->install($name);

            return response()->json([
                'message' => "Plugin [{$name}] installed successfully.",
                'data' => new PluginResource($plugin->load('settings')),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Plugin installation failed: '.$e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/admin/plugins/{name}/activate
     */
    public function activate(Request $request, string $name): JsonResponse
    {
        $this->authz->authorize($request->user(), 'plugins.manage');

        try {
            $plugin = $this->manager->activate($name);

            return response()->json([
                'message' => "Plugin [{$name}] activated successfully.",
                'data' => new PluginResource($plugin->load('settings')),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Plugin activation failed: '.$e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/admin/plugins/{name}/deactivate
     */
    public function deactivate(Request $request, string $name): JsonResponse
    {
        $this->authz->authorize($request->user(), 'plugins.manage');

        try {
            $plugin = $this->manager->deactivate($name);

            return response()->json([
                'message' => "Plugin [{$name}] deactivated successfully.",
                'data' => new PluginResource($plugin->load('settings')),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Plugin deactivation failed: '.$e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/admin/plugins/{name}/uninstall
     */
    public function uninstall(Request $request, string $name): JsonResponse
    {
        $this->authz->authorize($request->user(), 'plugins.manage');

        try {
            $this->manager->uninstall($name);

            return response()->json([
                'message' => "Plugin [{$name}] uninstalled successfully.",
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Plugin uninstallation failed: '.$e->getMessage()], 500);
        }
    }

    /**
     * PATCH /api/v1/admin/plugins/{name}/settings
     */
    public function updateSettings(Request $request, string $name): JsonResponse
    {
        $this->authz->authorize($request->user(), 'plugins.manage');

        $plugin = Plugin::where('name', $name)->firstOrFail();

        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['required'],
        ]);

        foreach ($validated['settings'] as $item) {
            $plugin->settings()->updateOrCreate(
                ['key' => $item['key'], 'space_id' => $request->input('space_id')],
                ['value' => $item['value']],
            );
        }

        return response()->json([
            'message' => "Settings for plugin [{$name}] updated successfully.",
            'data' => new PluginResource($plugin->load('settings')),
        ]);
    }
}
