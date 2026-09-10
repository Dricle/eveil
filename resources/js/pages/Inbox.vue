<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import StatusSelect from '@/components/StatusSelect.vue'
import { OUTREACH_STATUSES } from '@/lib/status'
import contactRoutes from '@/routes/contacts'
import { attention as attentionRoute, reply as replyRoute } from '@/routes/inbox'
import { inbox } from '@/routes'
import type { Conversation, Folder, Paginated } from '@/types'
import { CLASSIFICATIONS } from '@/types/inbox'

const props = defineProps<{
    conversations: Paginated<Conversation>
    campaigns: { id: number, name: string }[]
    folders: Folder[]
    filters: { campaign: number | null, folder: string }
}>()

// `0` rather than an empty string: reka reserves '' for clearing a select, and
// a SelectItem carrying it throws on mount.
const campaign = ref(props.filters.campaign ?? 0)

const CAMPAIGN_OPTIONS = computed(() => [
    { label: 'Every campaign', value: 0 },
    ...props.campaigns.map(item => ({ label: item.name, value: item.id }))
])

// A folder is a route segment, not a query param on one route: a param a
// pagination link can silently drop switches the screen back to the default
// folder mid-click, which is exactly the bug a segment makes impossible.
function go (next: { campaign?: number, folder?: string, page?: number } = {}) {
    const folder = next.folder ?? props.filters.folder
    const id = next.campaign ?? campaign.value

    router.get(inbox.url(folder), {
        ...(id ? { campaign: id } : {}),
        ...(next.page ? { page: next.page } : {})
    }, { preserveState: true, preserveScroll: true })
}

// One label and icon per folder key. Reuses the status vocabulary rather
// than inventing a second one: a folder IS a status, `sent` the one
// exception that is not.
const FOLDER_META: Record<string, { label: string, icon: string }> = Object.fromEntries([
    ...OUTREACH_STATUSES.map(status => [status.value, { label: status.label, icon: status.icon }]),
    ['sent', { label: 'Sent', icon: 'i-lucide-send' }]
])

// Every folder the server sent already excludes the ones that make no sense
// here (`new`/`queued`/`contacted`: nothing without a reply belongs on this
// screen). What is left is hidden only when it is BOTH empty and not the
// folder currently open: the two anchors (`replied`, `sent`) and wherever
// the user already is stay visible even at zero, so the strip is never just
// whichever folders happen to be full today.
const visibleFolders = computed(() => props.folders.filter(folder =>
    folder.total > 0 || folder.key === 'replied' || folder.key === 'sent' || folder.key === props.filters.folder
))

// Never opens itself: it used to jump to whichever conversation `needs_attention`,
// which reopened on every visit no matter what the user had already decided.
// Held as an id rather than the row itself: the list is replaced wholesale on
// every poll and every reload, and an id still finds the same conversation in
// the new array while a captured object would quietly stop updating.
const activeConversationId = ref<number | null>(null)

const activeConversation = computed(() => props.conversations.data.find(item => item.id === activeConversationId.value) ?? null)

// Nuxt UI's modal wants a boolean model; closing it (backdrop, Escape, the
// X) has to clear the id, or the next poll would silently reopen it.
const modalOpen = computed({
    get: () => activeConversation.value !== null,
    set: (value: boolean) => {
        if (!value) {
            activeConversationId.value = null
        }
    }
})

// The user's own verdict, both directions: "I've seen this" and "actually,
// put it back". A status change or a fresh reply still move it on their own
// (`SetOutreachStatus`, `FetchReplies::pause()`); this is the one path that
// can also go from done back to todo.
const togglingAttention = ref(false)

function toggleAttention (conversation: Conversation) {
    togglingAttention.value = true

    router.put(attentionRoute.url(conversation.id), { resolved: !conversation.resolved }, {
        preserveScroll: true,
        onFinish: () => { togglingAttention.value = false }
    })
}

// The classification is a permanent record of what the reply WAS ("Needs
// you", "Interested"...) and never changes once written. Left in its own
// color forever, it reads as a live alarm even on a conversation the user
// already decided - which is the confusion `needs_attention` itself was
// just fixed for. Neutralised the same way: once resolved, the label stays
// (it is still true, and still useful history) but stops shouting.
function verdict (conversation: Conversation) {
    if (!conversation.classification) {
        return null
    }

    const entry = CLASSIFICATIONS[conversation.classification]

    return conversation.needs_attention ? entry : { ...entry, color: 'neutral' as const }
}

function when (value: string | null) {
    return value === null ? '' : new Date(value).toLocaleString()
}

function time (value: string | null) {
    return value === null ? '' : new Date(value).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
}

function initial (conversation: Conversation) {
    return (conversation.lead.name ?? conversation.lead.email ?? '?').charAt(0).toUpperCase()
}

// The list is already ordered newest first; grouping only has to split it
// where the calendar day changes, not sort it.
const groups = computed(() => {
    const stamp = (conversation: Conversation) =>
        props.filters.folder === 'sent' ? conversation.sent_at : conversation.replied_at

    const result: { label: string, conversations: Conversation[] }[] = []

    for (const conversation of props.conversations.data) {
        const value = stamp(conversation)
        const label = value === null ? 'Undated' : new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'long' })
        const last = result[result.length - 1]

        if (last && last.label === label) {
            last.conversations.push(conversation)
        } else {
            result.push({ label, conversations: [conversation] })
        }
    }

    return result
})

// A refused send still leaves a row, on purpose: the attempt is a fact worth
// keeping. Showing it exactly like a delivered mail would tell somebody their
// mail went out when it never left the building.
const DELIVERY = {
    failed: { label: 'Not sent', color: 'error' as const, help: 'The mail server refused this one. Nobody received it.' },
    bounced: { label: 'Bounced', color: 'error' as const, help: 'The address rejected it. Nobody received it.' },
    queued: { label: 'Queued', color: 'neutral' as const, help: 'Waiting for its turn in the sending window.' }
}

function delivery (conversation: Conversation) {
    return conversation.delivery && conversation.delivery !== 'sent'
        ? DELIVERY[conversation.delivery]
        : null
}
</script>

<template>
    <AppLayout>
        <Head title="Inbox" />

        <div class="space-y-4 p-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="font-medium">
                        Inbox
                    </h2>
                    <p class="text-sm text-muted">
                        {{ filters.folder === 'sent'
                            ? 'Everything written to somebody, answered or not. A mail the server refused is here too, marked as such: the attempt is worth knowing about, and it is not the same thing as one that arrived.'
                            : 'Everyone who answered, across every mailbox, filed by where the conversation stands. An agent read each reply and did something about it; the rest is where you filed it after.' }}
                    </p>
                </div>

                <USelect
                    v-model="campaign"
                    :items="CAMPAIGN_OPTIONS"
                    class="w-64"
                    @update:model-value="value => go({ campaign: value })"
                />
            </div>

            <!-- One folder per status, plus `sent`: a mailbox's folder list,
                 not a single feed. `replied` is the front door - nothing
                 stays there once the user has filed it somewhere. -->
            <div class="flex flex-wrap items-center gap-2">
                <UButton
                    v-for="folder in visibleFolders"
                    :key="folder.key"
                    size="sm"
                    :icon="FOLDER_META[folder.key]?.icon"
                    :color="filters.folder === folder.key ? 'primary' : 'neutral'"
                    :variant="filters.folder === folder.key ? 'subtle' : 'outline'"
                    class="rounded-full"
                    @click="go({ folder: folder.key })"
                >
                    {{ FOLDER_META[folder.key]?.label ?? folder.key }}
                    <span class="text-xs opacity-60">{{ folder.total }}</span>
                    <UBadge
                        v-if="folder.needs_attention > 0"
                        color="primary"
                        variant="solid"
                        size="sm"
                        :label="folder.needs_attention"
                    />
                </UButton>
            </div>

            <p
                v-if="!conversations.data.length"
                class="rounded-lg p-6 text-sm text-muted ring ring-default"
            >
                <template v-if="filters.folder === 'sent'">
                    Nothing has been sent yet from this project.
                </template>
                <template v-else-if="filters.folder === 'replied'">
                    Nobody has replied yet. Only real answers land here: a lead that
                    was written to and said nothing is a sequence still running, not
                    an inbox entry.
                </template>
                <template v-else>
                    Nothing filed here yet.
                </template>
            </p>

            <template
                v-for="group in groups"
                :key="group.label"
            >
                <div class="mb-2 font-mono text-[10px] font-medium tracking-wider text-dimmed uppercase">
                    {{ group.label }}
                </div>

                <div class="mb-6 space-y-2">
                    <div
                        v-for="conversation in group.conversations"
                        :key="conversation.id"
                        class="overflow-hidden rounded-lg ring ring-default"
                        :class="conversation.needs_attention ? 'ring-primary' : ''"
                    >
                        <button
                            type="button"
                            class="flex w-full flex-wrap items-start gap-3 p-4 text-left"
                            @click="activeConversationId = conversation.id"
                        >
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-elevated text-xs font-semibold text-toned">
                                {{ initial(conversation) }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="mb-0.5 flex items-baseline gap-2">
                                    <span class="font-medium text-highlighted">{{ conversation.lead.name ?? conversation.lead.email }}</span>
                                    <span
                                        v-if="conversation.lead.company"
                                        class="min-w-0 truncate text-sm text-dimmed"
                                    >{{ conversation.lead.company }}</span>
                                    <span class="ms-auto shrink-0 font-mono text-xs text-dimmed">{{
                                        time(filters.folder === 'sent' ? conversation.sent_at : conversation.replied_at)
                                    }}</span>
                                </div>
                                <p class="mb-2 truncate text-sm text-muted">
                                    {{ conversation.messages[conversation.messages.length - 1]?.body }}
                                </p>

                                <div class="flex flex-wrap items-center gap-1.5">
                                    <UBadge
                                        v-if="verdict(conversation)"
                                        :color="verdict(conversation)!.color"
                                        variant="subtle"
                                        size="sm"
                                        :label="verdict(conversation)!.label"
                                        :title="verdict(conversation)!.help"
                                    />

                                    <UBadge
                                        v-if="delivery(conversation)"
                                        :color="delivery(conversation)!.color"
                                        variant="subtle"
                                        size="sm"
                                        icon="i-lucide-mail-x"
                                        :label="delivery(conversation)!.label"
                                        :title="delivery(conversation)!.help"
                                    />

                                    <UBadge
                                        color="neutral"
                                        variant="outline"
                                        size="sm"
                                        :label="conversation.campaign.name"
                                    />
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </template>

            <div
                v-if="conversations.meta.last_page > 1"
                class="flex justify-center"
            >
                <UPagination
                    :default-page="conversations.meta.current_page"
                    :items-per-page="conversations.meta.per_page"
                    :total="conversations.meta.total"
                    @update:page="page => go({ page })"
                />
            </div>
        </div>

        <!-- The thread, opened on top of everything: who they are and where
             they stand comes first, the messages read top to bottom like an
             actual mailbox, and answering never means leaving this screen. -->
        <UModal
            v-model:open="modalOpen"
            :ui="{ content: 'max-w-2xl' }"
        >
            <template
                v-if="activeConversation"
                #content
            >
                <div class="flex flex-col">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-default p-4">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-elevated text-sm font-semibold text-toned">
                                {{ initial(activeConversation) }}
                            </span>

                            <div class="min-w-0 space-y-0.5">
                                <ULink
                                    :href="contactRoutes.show.url(activeConversation.lead.id)"
                                    class="font-medium text-highlighted"
                                >{{ activeConversation.lead.name ?? activeConversation.lead.email }}</ULink>
                                <p
                                    v-if="activeConversation.lead.title || activeConversation.lead.company"
                                    class="text-sm text-muted"
                                >
                                    {{ [activeConversation.lead.title, activeConversation.lead.company].filter(Boolean).join(' · ') }}
                                </p>
                                <p
                                    v-if="activeConversation.lead.email"
                                    class="truncate font-mono text-xs text-dimmed"
                                >
                                    {{ activeConversation.lead.email }}
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-1.5">
                            <UBadge
                                color="neutral"
                                variant="outline"
                                size="sm"
                                :label="activeConversation.campaign.name"
                            />
                            <StatusSelect
                                :status="activeConversation.lead.status"
                                :options="OUTREACH_STATUSES"
                                :url="contactRoutes.status.url(activeConversation.lead.id)"
                            />
                            <!-- The user's own verdict, separate from status:
                                 whether THEY have looked at this one. -->
                            <UButton
                                :icon="activeConversation.resolved ? 'i-lucide-rotate-ccw' : 'i-lucide-check'"
                                :color="activeConversation.resolved ? 'neutral' : 'primary'"
                                :variant="activeConversation.resolved ? 'outline' : 'solid'"
                                size="sm"
                                :label="activeConversation.resolved ? 'Mark as todo' : 'Mark as done'"
                                :loading="togglingAttention"
                                @click="toggleAttention(activeConversation)"
                            />
                            <UButton
                                icon="i-lucide-x"
                                color="neutral"
                                variant="ghost"
                                size="sm"
                                @click="modalOpen = false"
                            />
                        </div>
                    </div>

                    <div class="max-h-[60vh] space-y-3 overflow-y-auto p-4">
                        <div
                            v-for="message in activeConversation.messages"
                            :key="message.id"
                            class="rounded-lg p-3 text-sm"
                            :class="message.direction === 'inbound' ? 'bg-elevated' : 'ring ring-default'"
                        >
                            <p class="mb-1 text-xs text-dimmed">
                                {{ message.direction === 'inbound' ? 'Them' : 'You' }} · {{ when(message.at) }} · {{ message.subject }}
                                <span
                                    v-if="message.direction === 'outbound' && message.status && message.status !== 'sent'"
                                    class="text-error"
                                >· never left: {{ message.status }}</span>
                            </p>
                            <p class="whitespace-pre-wrap">
                                {{ message.body }}
                            </p>
                        </div>
                    </div>

                    <!-- Answering by hand stops the sequence: somebody being
                         written to by a person must not also receive the
                         follow-up queued behind them. -->
                    <Form
                        v-slot="{ errors, processing }"
                        v-bind="replyRoute.form(activeConversation.id)"
                        class="space-y-2 border-t border-default p-4"
                        :options="{ preserveScroll: true }"
                    >
                        <UFormField
                            name="body"
                            :error="errors.body"
                            :help="filters.folder === 'sent'
                                ? 'Sent from the same mailbox, in the same thread. Writing by hand stops the sequence: nobody should get your mail and the queued follow-up as well.'
                                : 'Sent from the same mailbox, in the same thread. Your signature is added if the mailbox has one.'"
                        >
                            <UTextarea
                                name="body"
                                :rows="4"
                                placeholder="Write back…"
                                class="w-full"
                            />
                        </UFormField>

                        <UButton
                            type="submit"
                            :loading="processing"
                            :label="filters.folder === 'sent' ? 'Send and stop the sequence' : 'Send reply'"
                        />
                    </Form>
                </div>
            </template>
        </UModal>
    </AppLayout>
</template>
