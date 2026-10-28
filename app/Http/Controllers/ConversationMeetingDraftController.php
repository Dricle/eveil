<?php

namespace App\Http\Controllers;

use App\Actions\DraftMeetingProposal;
use App\Models\CampaignLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * A draft answer offering times, handed back to the reply box rather than
 * rendered as a page: the user is mid-conversation, and what comes back is
 * text for them to edit, not a new state of the inbox. Nothing is sent from
 * here.
 */
class ConversationMeetingDraftController extends Controller
{
    public function store(DraftMeetingProposal $draft, int $conversation): JsonResponse
    {
        // Confined to the current project through `whereHas('campaign')`, the
        // same guard `ConversationReplyController` uses.
        $membership = CampaignLead::query()
            ->whereHas('campaign')
            ->with(['campaign.project', 'lead.company', 'messages.emailAccount'])
            ->findOrFail($conversation);

        try {
            return response()->json($draft->handle($membership));
        } catch (RuntimeException $e) {
            // Hours not set, nothing free, no credit left: each is something
            // the user can act on, so it comes back as a field error the
            // reply box shows, not as a 500.
            throw ValidationException::withMessages(['draft' => $e->getMessage()]);
        }
    }
}
