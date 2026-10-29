<?php

namespace App\Models;

use App\Enums\CampaignLeadStatus;
use App\Enums\MessageDirection;
use App\Enums\OutreachStatus;
use Database\Factories\CampaignLeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One lead's journey through one campaign. The email account is pinned for the
 * whole sequence so mailbox rotation never splits a conversation.
 *
 * A lead sits in at most one live membership: the database enforces it with a
 * partial unique index.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $lead_id
 * @property int|null $email_account_id
 * @property int $current_step_position
 * @property CampaignLeadStatus $status
 * @property Carbon|null $next_action_at
 * @property Carbon|null $paused_at
 * @property string|null $pause_reason
 * @property Carbon|null $attention_resolved_at set once a person has dealt with this conversation - by the user's own toggle, or automatically the moment they set a status. Reset to null whenever a new reply arrives, whatever it said last time.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['campaign_id', 'lead_id', 'email_account_id', 'current_step_position', 'status', 'next_action_at', 'paused_at', 'pause_reason', 'attention_resolved_at'])]
class CampaignLead extends Model
{
    /** @use HasFactory<CampaignLeadFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Both directions of the conversation, oldest first. Which is the order a
     * thread reads in, and the order a reply needs its own question in.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    /**
     * What actually left for this membership, which is not the same as the step
     * they are on: a step whose send failed moved the position and delivered
     * nothing.
     *
     * @return HasMany<Message, $this>
     */
    public function sentMessages(): HasMany
    {
        return $this->messages()
            ->where('direction', MessageDirection::Outbound)
            ->whereNotNull('sent_at');
    }

    /**
     * @return BelongsTo<EmailAccount, $this>
     */
    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    /**
     * One folder of the inbox screen: an exact match on the lead's actual
     * status, whatever it is, plus `sent` for everything mailed out
     * regardless of status. Every folder but `sent` requires an inbound
     * message - a lead written to and never answered is a sequence still
     * running, not something to file anywhere yet.
     *
     * `contacted` is a real folder here on purpose, not a status filtered out
     * of some catch-all: an out-of-office deliberately never pauses the
     * sequence (`FetchReplies::record()`), so its lead's status stays
     * whatever it already was - `contacted`, most often - never `replied`.
     * An exact match everywhere means that reply shows up exactly where its
     * lead actually stands instead of needing a special case to avoid
     * disappearing off the screen entirely.
     *
     * @param  Builder<CampaignLead>  $query
     */
    #[Scope]
    protected function inFolder(Builder $query, string $folder): void
    {
        if ($folder === 'sent') {
            $query->whereHas('messages', fn (Builder $messages) => $messages->where('direction', MessageDirection::Outbound));

            return;
        }

        $query->whereHas('messages', fn (Builder $messages) => $messages->where('direction', MessageDirection::Inbound))
            ->whereHas('lead', fn (Builder $lead) => $lead->where('status', OutreachStatus::from($folder)));
    }

    /**
     * The order the delivery screen shows one campaign's people in: whoever
     * is owed something first, then the rest by how recently anything moved.
     *
     * @param  Builder<CampaignLead>  $query
     */
    #[Scope]
    protected function orderedForDeliveryScreen(Builder $query): void
    {
        $query->orderByRaw('next_action_at is null')
            ->orderBy('next_action_at')
            ->orderByDesc('id');
    }

    /**
     * The order the inbox shows conversations in: newest activity first,
     * like Gmail. `updated_at` is the wrong column for this - it moves on a
     * status change or an `attention_resolved_at` toggle, neither of which is
     * a new message - so this reads the actual last message time instead,
     * the same `sent_at ?? received_at ?? created_at` fallback
     * `ConversationResource` uses per message.
     *
     * @param  Builder<CampaignLead>  $query
     */
    #[Scope]
    protected function orderedByLastActivity(Builder $query): void
    {
        $query->orderByDesc(
            Message::query()
                ->selectRaw('MAX(COALESCE(sent_at, received_at, created_at))')
                ->whereColumn('campaign_lead_id', 'campaign_leads.id')
        );
    }

    /**
     * Whether a person still has to look at this conversation. The single
     * definition `ConversationResource` (one row), `InboxFolders` (every
     * badge count, including the sidebar's) and this class all read, so none
     * of them can quietly disagree about what "needs attention" means.
     *
     * A reply with no classification yet counts as a todo too, not just the
     * ones the agent has already read as `interested`/`needs_human`/
     * `wrong_person`. `HandleReply` classifies asynchronously - `FetchReplies`
     * only pauses the sequence and clears `attention_resolved_at` - so a
     * reply sits briefly with `classification === null` before the agent's
     * verdict lands. Waiting for that verdict before showing the todo means
     * a fresh reply is invisible for however long the queue takes to get to
     * it, which is backwards: it exists and nobody has looked, which is
     * already "todo" on its own. An auto-reply is the one kind that never
     * passes through here with a null classification - `FetchReplies`
     * writes `AutoReply` synchronously, before `attention_resolved_at` is
     * ever touched - so this never mistakes one for a todo.
     *
     * Requires `messages` loaded: reads the last inbound one without a query.
     */
    public function needsAttention(): bool
    {
        if ($this->attention_resolved_at !== null) {
            return false;
        }

        $lastInbound = $this->messages->last(fn (Message $message): bool => $message->direction->isInbound());

        if ($lastInbound === null) {
            return false;
        }

        return $lastInbound->classification === null || $lastInbound->classification->needsAttention();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignLeadStatus::class,
            'next_action_at' => 'datetime',
            'paused_at' => 'datetime',
            'attention_resolved_at' => 'datetime',
        ];
    }
}
