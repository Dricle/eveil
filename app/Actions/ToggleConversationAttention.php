<?php

namespace App\Actions;

use App\Models\CampaignLead;

/**
 * The user's own verdict on whether a conversation still needs them, told to
 * the app explicitly rather than inferred. A status change or a fresh reply
 * still move it automatically (`SetOutreachStatus`, `FetchReplies::pause()`);
 * this is the third way it can change, and the only one that goes both
 * directions - "I've seen this" is reversible, unlike a status.
 */
class ToggleConversationAttention
{
    public function handle(CampaignLead $conversation, bool $resolved): void
    {
        $conversation->update(['attention_resolved_at' => $resolved ? now() : null]);
    }
}
