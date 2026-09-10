<?php

namespace App\Http\Controllers;

use App\Actions\ToggleConversationAttention;
use App\Http\Requests\ConversationAttentionRequest;
use App\Models\CampaignLead;
use Illuminate\Http\RedirectResponse;

/**
 * The user saying "I've seen this" or putting it back on the list. Its own
 * route, not folded into a status: a status is a verdict on the LEAD, this is
 * whether a person has looked at ONE conversation, and the two move
 * independently in one direction (a fresh reply always reopens this,
 * whatever the lead's status already says).
 *
 * No validation rule on the id: `whereHas('campaign')` already answers
 * whether this conversation belongs to the current project, and anything
 * else is a genuine 404 - same reasoning as `ConversationReplyController`.
 */
class ConversationAttentionController extends Controller
{
    public function update(ConversationAttentionRequest $request, ToggleConversationAttention $toggle, int $conversation): RedirectResponse
    {
        $toggle->handle(
            CampaignLead::query()->whereHas('campaign')->findOrFail($conversation),
            $request->boolean('resolved'),
        );

        return back();
    }
}
