<?php

namespace App\Http\Controllers;

use App\Ai\Agents\Evie;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Responses\StreamableAgentResponse;

/**
 * The persistent chat panel's backend: one conversation per project, streamed
 * turn by turn. Not an Inertia screen - the panel lives outside any one
 * page's props, so this is a plain JSON/SSE endpoint the frontend's own
 * fetch-based stream client talks to directly.
 */
class EvieChatController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    /**
     * The transcript to render on panel mount or project switch: whatever the
     * project's last conversation holds, or nothing if it has never chatted.
     */
    public function index(ConversationStore $store): JsonResponse
    {
        $project = $this->currentProject->getOrFail();

        $agent = (new Evie($project))->continueLastConversation($project);

        $conversationId = $agent->currentConversation();

        if ($conversationId === null) {
            return response()->json(['conversation_id' => null, 'messages' => []]);
        }

        // A stored turn reconstructs into more than one Message: a tool call
        // announces itself as a content-less AssistantMessage, its outcome as
        // a ToolResultMessage - neither is prose worth showing in this
        // simplified transcript. A user turn with no attachments comes back
        // as the plain base `Message` class, not `UserMessage`, so the filter
        // reads the role rather than the class.
        $messages = $store->getLatestConversationMessages($conversationId, 100)
            ->filter(fn (Message $message): bool => in_array($message->role->value, ['user', 'assistant'], true)
                && filled($message->content))
            ->map(fn (Message $message): array => [
                'role' => $message->role->value,
                'content' => $message->content,
            ])
            ->values();

        return response()->json(['conversation_id' => $conversationId, 'messages' => $messages]);
    }

    /**
     * One turn: either a plain message or a resume with the user's approval
     * decisions on a paused turn. Always continues the project's one
     * conversation - `destroy()` is what starts a fresh one, by making an
     * empty conversation the project's latest before this is ever called.
     */
    public function store(Request $request): StreamableAgentResponse
    {
        $project = $this->currentProject->getOrFail();

        $agent = (new Evie($project))->continueLastConversation($project);

        $decisions = $request->array('decisions');

        $prompt = $decisions !== []
            ? Decisions::from($decisions)
            : $request->string('message')->value();

        return $agent->stream($prompt)->usingVercelDataProtocol(true);
    }

    /**
     * "Clear": rather than only a local reset the next message would have to
     * carry, this makes a brand new, empty conversation the project's latest
     * right away - so a reload before that next message shows nothing, not
     * whatever the previous conversation still holds. `index()` and `store()`
     * both always continue "the latest conversation", so creating an empty
     * one here is the whole mechanism; neither needs to know "clear" happened.
     */
    public function destroy(ConversationStore $store): JsonResponse
    {
        $project = $this->currentProject->getOrFail();

        $store->storeConversation(
            Conversation::participantType($project),
            Conversation::participantKey($project),
            'New conversation',
        );

        return response()->json(['ok' => true]);
    }
}
