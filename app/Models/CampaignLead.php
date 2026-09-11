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
     * One folder of the inbox screen: every status a lead can be filed
     * under, plus `sent` for everything mailed out regardless of status.
     * Every folder but `sent` requires an inbound message - a lead written
     * to and never answered is a sequence still running, not something to
     * file anywhere yet.
     *
     * `replied` is NOT an exact match on `status = 'replied'`: it is
     * everything with a reply that has not been filed anywhere yet - one of
     * the five terminal statuses (`OutreachStatus::excluded()`) or the
     * non-terminal `in_discussion`, which is as much a filing decision as the
     * five even though it does not block future outreach. An out-of-office
     * deliberately never pauses the sequence (`FetchReplies::record()`), so
     * its lead's status stays whatever it already was - `contacted`, most
     * often - never `replied`. An exact match on `replied` made those
     * conversations vanish from every folder at once: they had answered,
     * were not filed anywhere, and matched none of the folders. The front
     * door has to catch anything not yet decided, whatever status it happens
     * to be parked at, or a reply the classifier read as automatic
     * disappears from the screen entirely.
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

        $query->whereHas('messages', fn (Builder $messages) => $messages->where('direction', MessageDirection::Inbound));

        if ($folder === 'replied') {
            $filed = [...OutreachStatus::excluded(), OutreachStatus::InDiscussion];
            $query->whereHas('lead', fn (Builder $lead) => $lead->whereNotIn('status', $filed));

            return;
        }

        $query->whereHas('lead', fn (Builder $lead) => $lead->where('status', OutreachStatus::from($folder)));
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
