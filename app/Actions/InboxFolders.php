<?php

namespace App\Actions;

use App\Enums\MessageDirection;
use App\Models\CampaignLead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * One query shape, reused for the currently open folder's paginated list and
 * for every folder's sidebar count: the two must never compute "what is in
 * this folder" two different ways, or a badge would read one number while
 * the list underneath it showed another.
 */
class InboxFolders
{
    /**
     * `new`/`queued`/`contacted` are real values a lead can sit at, but never
     * ones a REPLY does: nothing reaches this screen without an inbound
     * message, and none of those three describe a lead who sent one. Left
     * out of the list entirely rather than shown permanently empty.
     *
     * `replied` first: it is the default folder, and the one a fresh answer
     * lands in before anybody has decided what it is.
     *
     * @var list<string>
     */
    public const FOLDERS = ['replied', 'won', 'lost', 'client', 'rejected', 'suppressed', 'sent'];

    /**
     * @return Builder<CampaignLead>
     */
    public function query(Request $request, string $folder): Builder
    {
        return CampaignLead::query()
            ->inFolder($folder)
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

    /**
     * @return array<int, array{key: string, total: int, needs_attention: int}>
     */
    public function summaries(Request $request): array
    {
        return collect(self::FOLDERS)
            ->map(function (string $folder) use ($request): array {
                $rows = $this->query($request, $folder)->get();

                return [
                    'key' => $folder,
                    'total' => $rows->count(),
                    // Meaningless for `sent`: it holds everything mailed
                    // out, answered or not, and "needs a person" is a reply
                    // concept.
                    'needs_attention' => $folder === 'sent' ? 0 : $rows->filter->needsAttention()->count(),
                ];
            })
            ->all();
    }

    /**
     * Every conversation that still needs a person, across every folder at
     * once - the sidebar's badge. Not scoped to `replied`: nothing in the
     * schema forbids a lead replying again after already being filed
     * somewhere, and that reply deserves someone's attention regardless of
     * which folder it happens to sit in.
     */
    public function todoCount(): int
    {
        return CampaignLead::query()
            ->whereHas('messages', fn (Builder $messages) => $messages->where('direction', MessageDirection::Inbound))
            ->whereHas('campaign')
            ->with('messages')
            ->get()
            ->filter->needsAttention()
            ->count();
    }
}
