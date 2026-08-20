<?php

namespace App\Http\Controllers;

use App\Enums\MessageDirection;
use App\Http\Resources\ConversationResource;
use App\Models\Campaign;
use App\Models\CampaignLead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Everyone who actually answered, across every mailbox this project sends from.
 *
 * Only real conversations: a lead that was written to and said nothing is not an
 * inbox entry, it is a sequence still running. That is what keeps this screen
 * worth opening: the alternative is a list of five hundred rows where four are
 * interesting.
 *
 * Ordered by what needs a person rather than by date. An interested reply from
 * Tuesday outranks an out-of-office from this morning, and the agent has already
 * said which is which.
 */
class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        $conversations = CampaignLead::query()
            ->whereHas('messages', fn (Builder $messages) => $messages->where('direction', MessageDirection::Inbound))
            ->with([
                'campaign',
                'lead.company',
                'messages' => fn ($messages) => $messages->orderBy('id'),
            ])
            ->when($request->integer('campaign'), fn (Builder $query, int $id) => $query->where('campaign_id', $id))
            // `campaign_leads` carries no `project_id`, so this is what scopes
            // the screen: the global scope on `Campaign` applies inside the
            // relation query, and without it one project's inbox would show
            // another's replies.
            ->whereHas('campaign')
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Inbox', [
            'conversations' => ConversationResource::collection($conversations),
            'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'campaign' => $request->integer('campaign') ?: null,
            ],
        ]);
    }
}
