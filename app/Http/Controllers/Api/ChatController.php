<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendChatMessageRequest;
use App\Models\ChatConversation;
use App\Services\Chat\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
    ) {}

    /**
     * List user's conversations (paginated).
     */
    public function conversations(Request $request): JsonResponse
    {
        $conversations = ChatConversation::where('user_id', $request->user()->id)
            ->orderByDesc('last_active_at')
            ->paginate(20);

        return response()->json(['data' => $conversations]);
    }

    /**
     * Create a new conversation.
     */
    public function createConversation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'space_id' => ['required', 'string', 'exists:spaces,id'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $conversation = ChatConversation::create([
            'space_id' => $data['space_id'],
            'user_id' => $user->id,
            'title' => $data['title'] ?? null,
            'last_active_at' => now(),
        ]);

        return response()->json(['data' => $conversation], 201);
    }

    /**
     * Delete a conversation.
     */
    public function deleteConversation(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $conversation = ChatConversation::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $conversation->delete();

        return response()->json(null, 204);
    }

    /**
     * Get message history for a conversation (paginated).
     */
    public function messages(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $conversation = ChatConversation::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->paginate(50);

        return response()->json(['data' => $messages]);
    }

    /**
     * Send a message and stream the assistant's response via SSE.
     */
    public function sendMessage(SendChatMessageRequest $request, string $id): StreamedResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $conversation = ChatConversation::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $space = $conversation->space;
        $message = $request->validated('message');

        $generator = $this->conversationService->handle(
            user: $user,
            space: $space,
            conversationId: $conversation->id,
            message: $message,
        );

        return response()->stream(function () use ($generator): void {
            foreach ($generator as $chunk) {
                echo 'data: '.json_encode($chunk)."\n\n";
                ob_flush();
                flush();
            }
            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Execute the pending action for a conversation.
     */
    public function confirmAction(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $conversation = ChatConversation::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (! $conversation->pending_action) {
            return response()->json(['error' => 'No pending action'], 422);
        }

        $pendingAction = $conversation->pending_action;

        $conversation->update(['pending_action' => null]);

        return response()->json([
            'data' => [
                'confirmed' => true,
                'action' => $pendingAction,
            ],
        ]);
    }

    /**
     * Cancel the pending action for a conversation.
     */
    public function cancelAction(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $conversation = ChatConversation::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $conversation->update(['pending_action' => null]);

        return response()->json([
            'data' => [
                'cancelled' => true,
            ],
        ]);
    }
}
