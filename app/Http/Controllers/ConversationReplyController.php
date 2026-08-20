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
        $reply->handle(
            CampaignLead::query()->with(['lead', 'emailAccount', 'messages'])->findOrFail($conversation),
            $request->string('body')->value(),
        );

        return back();
    }
}
