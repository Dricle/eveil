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
 * Only real conversations in the default list: a lead that was written to and
 * said nothing is not an inbox entry, it is a sequence still running. That is
 * what keeps this screen worth opening, and the alternative is a list of five
 * hundred rows where four are interesting.
 *
 * Everything that LEFT is the second list, on its own route (`inbox.sent`).
 * It answers a different question, "did anything actually go out and what did
 * it say", which could otherwise only be answered one contact sheet at a time.
 *
 * Ordered by what needs a person rather than by date. An interested reply from
 * Tuesday outranks an out-of-office from this morning, and the agent has already
 * said which is which.
 */
class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render($request, sent: false);
    }

    /**
     * Everything that LEFT, its own route rather than a query param on
     * `index`: a param a pagination link can silently drop switches the
     * screen back to Replies mid-click, which is exactly the bug this route
     * exists to make impossible.
     */
    public function sent(Request $request): Response
    {
        return $this->render($request, sent: true);
    }

    private function render(Request $request, bool $sent): Response
    {
        $conversations = $this->conversations($request, $sent)
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Inbox', [
            'conversations' => ConversationResource::collection($conversations),
            'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name']),
            // Both counts, always, so the tab that is not open still says
            // whether it has anything in it. "Sent" reading zero while mails
            // are going out is the confusion this screen is meant to end.
            'counts' => [
                'replies' => $this->conversations($request, false)->count(),
                'sent' => $this->conversations($request, true)->count(),
            ],
            'filters' => [
                'campaign' => $request->integer('campaign') ?: null,
                'view' => $sent ? 'sent' : 'replies',
            ],
        ]);
    }

    /**
     * The two lists this screen holds, which differ by one condition.
     *
     * Replies is the default and the reason the screen exists. Sent is
     * everything that left, answered or not, because until now a mail nobody
     * answered could only be read by opening its contact sheet one at a time,
     * and "did anything actually go out" is the first question anybody asks.
     *
     * They stay two lists rather than one: mixing them would put five hundred
     * silent rows around the four that need a person, which is exactly what
     * keeps this screen worth opening.
     *
     * @return Builder<CampaignLead>
     */
    private function conversations(Request $request, bool $sent): Builder
    {
        return CampaignLead::query()
            ->whereHas('messages', fn (Builder $messages) => $messages
                ->where('direction', $sent ? MessageDirection::Outbound : MessageDirection::Inbound))
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
            ->whereHas('campaign');
    }
}
