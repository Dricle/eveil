<?php

namespace App\Actions;

use App\Enums\MessageDirection;
use App\Enums\OutreachStatus;
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
     * One folder per status a reply can leave a lead at (`OutreachStatus::reachableByReply()`,
     * `replied` first since it's declared first), plus `sent` - the one
     * folder that is not a status at all.
     *
     * @return list<string>
     */
    public static function folders(): array
    {
        return [
            ...array_map(fn (OutreachStatus $status): string => $status->value, OutreachStatus::reachableByReply()),
            'sent',
        ];
    }

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
        return collect(self::folders())
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
