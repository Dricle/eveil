<?php

namespace App\Http\Controllers;

use App\Actions\ReplyToConversation;
use App\Http\Requests\ReplyRequest;
use App\Models\CampaignLead;
use Illuminate\Http\RedirectResponse;

/**
 * The user writing back, in their own words, from their own mailbox.
 *
 * A separate route from the inbox screen because it is a different resource: a
 * message being created, and because this is the one place in the product where
 * a mail is composed by a person rather than an agent.
 */
class ConversationReplyController extends Controller
{
    public function store(ReplyRequest $request, ReplyToConversation $reply, int $conversation): RedirectResponse
    {
        // `CampaignLead` carries no `project_id` of its own: `whereHas('campaign')`
        // is what confines this to the current project rather than any id on the
        // instance, the same guard `ConversationAttentionController` uses.
        $reply->handle(
            CampaignLead::query()->whereHas('campaign')->with(['lead', 'emailAccount', 'messages'])->findOrFail($conversation),
            $request->string('body')->value(),
        );

        return back();
    }
}
