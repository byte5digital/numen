<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonaController extends Controller
{
    public function __construct(private AuthorizationService $authz) {}

    /**
     * List active personas. Requires persona.view permission.
     * Restricted to authorised users — exposes system prompts and model assignments.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authz->authorize($request->user(), 'persona.view');

        $personas = Persona::where('is_active', true)->get();

        return response()->json(['data' => $personas]);
    }
}
