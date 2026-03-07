<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PersonaAdminController extends Controller
{
    private array $availableModels = [
        'anthropic' => [
            'claude-opus-4-6', 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001',
            'claude-opus-4', 'claude-sonnet-4',
        ],
        'openai' => [
            'gpt-5.4', 'gpt-5.2', 'gpt-5.1', 'gpt-5', 'gpt-5-mini', 'gpt-5-nano',
            'gpt-4.1', 'gpt-4.1-mini', 'gpt-4.1-nano',
            'o3', 'o4-mini', 'gpt-4o', 'gpt-4o-mini',
        ],
        'azure' => [
            'gpt-5.4', 'gpt-5.2', 'gpt-5.1', 'gpt-5', 'gpt-5-mini',
            'gpt-4.1', 'gpt-4.1-mini', 'gpt-4o', 'gpt-4o-mini',
        ],
    ];

    private array $availableImageModels = [
        'openai' => ['gpt-image-1.5', 'gpt-image-1', 'gpt-image-1-mini'],
        'together' => ['black-forest-labs/FLUX.1-schnell', 'black-forest-labs/FLUX.1-pro'],
        'fal' => ['fal-ai/flux/schnell', 'fal-ai/flux-pro'],
        'replicate' => ['black-forest-labs/flux-2-max', 'black-forest-labs/flux-2-pro', 'openai/gpt-image-1.5', 'google/nano-banana-pro'],
    ];

    public function index()
    {
        return Inertia::render('Personas/Index', [
            'personas' => Persona::where('is_active', true)->get(),
            'availableModels' => $this->availableModels,
            'availableImageModels' => $this->availableImageModels,
        ]);
    }

    public function update(string $id, Request $request)
    {
        $persona = Persona::findOrFail($id);

        $data = $request->validate([
            'model_config' => 'required|array',
            'model_config.model' => 'nullable|string',
            'model_config.provider' => 'nullable|string|in:anthropic,openai,azure',
            'model_config.fallback_model' => 'nullable|string',
            'model_config.fallback_provider' => 'nullable|string|in:anthropic,openai,azure',
            'model_config.temperature' => 'nullable|numeric|min:0|max:2',
            'model_config.max_tokens' => 'nullable|integer|min:256|max:32768',
            'model_config.prompt_model' => 'nullable|string',
            'model_config.prompt_provider' => 'nullable|string|in:anthropic,openai,azure',
            'model_config.generator_model' => 'nullable|string',
            'model_config.generator_provider' => 'nullable|string|in:openai,together,fal,replicate',
            'model_config.size' => 'nullable|string',
            'model_config.style' => 'nullable|string',
            'model_config.quality' => 'nullable|string',
        ]);

        $persona->update([
            'model_config' => array_merge(
                $persona->model_config ?? [],
                $data['model_config'],
            ),
        ]);

        return back()->with('success', "Persona '{$persona->name}' updated.");
    }
}
