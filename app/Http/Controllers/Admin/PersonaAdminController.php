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
            'claude-opus-4', 'claude-sonnet-4', 'claude-3-5-sonnet-20241022',
        ],
        'openai' => [
            'gpt-5', 'gpt-5-mini', 'gpt-4.1', 'gpt-4.1-mini', 'gpt-4.1-nano',
            'gpt-4.5-preview', 'gpt-4o', 'gpt-4o-mini', 'o4-mini', 'o3', 'o3-mini', 'o1',
        ],
        'azure' => [
            'gpt-5', 'gpt-5-mini', 'gpt-4.1', 'gpt-4.1-mini', 'gpt-4o', 'gpt-4o-mini',
        ],
    ];

    public function index()
    {
        return Inertia::render('Personas/Index', [
            'personas' => Persona::where('is_active', true)->get(),
            'availableModels' => $this->availableModels,
        ]);
    }

    public function update(string $id, Request $request)
    {
        $persona = Persona::findOrFail($id);

        $data = $request->validate([
            'model_config' => 'required|array',
            'model_config.model' => 'required|string',
            'model_config.provider' => 'nullable|string|in:anthropic,openai,azure',
            'model_config.fallback_model' => 'nullable|string',
            'model_config.fallback_provider' => 'nullable|string|in:anthropic,openai,azure',
            'model_config.temperature' => 'required|numeric|min:0|max:2',
            'model_config.max_tokens' => 'required|integer|min:256|max:32768',
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
