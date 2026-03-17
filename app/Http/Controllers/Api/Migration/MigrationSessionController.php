<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Migration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Migration\MigrationSessionRequest;
use App\Http\Resources\Migration\MigrationSessionResource;
use App\Models\Migration\MigrationSession;
use App\Models\Space;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class MigrationSessionController extends Controller
{
    public function index(Space $space): AnonymousResourceCollection
    {
        $sessions = MigrationSession::where('space_id', $space->id)
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate(20);

        return MigrationSessionResource::collection($sessions);
    }

    public function store(MigrationSessionRequest $request, Space $space): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $session = MigrationSession::create([
            'space_id' => $space->id,
            'created_by' => $user->id,
            'name' => $request->validated('name'),
            'source_cms' => $request->validated('source_cms'),
            'source_url' => $request->validated('source_url'),
            'source_version' => $request->validated('source_version'),
            'credentials' => $request->validated('credentials'),
            'options' => $request->validated('options'),
            'status' => 'pending',
        ]);

        return (new MigrationSessionResource($session))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Space $space, string $migrationId): MigrationSessionResource
    {
        $session = MigrationSession::findOrFail($migrationId);
        abort_unless($session->space_id === $space->id, 404);

        $session->load('typeMappings');
        $session->loadCount('items');

        return new MigrationSessionResource($session);
    }

    public function update(MigrationSessionRequest $request, Space $space, string $migrationId): MigrationSessionResource
    {
        $session = MigrationSession::findOrFail($migrationId);
        abort_unless($session->space_id === $space->id, 404);

        $session->update($request->validated());

        return new MigrationSessionResource($session->fresh());
    }

    public function destroy(Space $space, string $migrationId): JsonResponse
    {
        $session = MigrationSession::findOrFail($migrationId);
        abort_unless($session->space_id === $space->id, 404);

        $session->delete();

        return response()->json(['message' => 'Migration session deleted.'], 200);
    }
}
