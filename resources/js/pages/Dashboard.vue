<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import ConversationPanel from '@/components/ConversationPanel.vue'
import LinkedinPostCard from '@/components/LinkedinPostCard.vue'
import RedditThreadCard from '@/components/RedditThreadCard.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { openEvieChat } from '@/composables/useChatPanel'
import { groupThreads } from '@/lib/reddit'
import { relativeUrl } from '@/lib/utils'
import { inbox, onboarding as onboardingRoute } from '@/routes'
import campaignRoutes from '@/routes/campaigns'
import companies from '@/routes/companies'
import discoveryRuns from '@/routes/discovery-runs'
import recommendationRoutes from '@/routes/recommendations'
import organizationBilling from '@/routes/settings/organization/billing'
import mailboxSettings from '@/routes/settings/mailboxes'
import autonomySettings from '@/routes/settings/autonomy'
import targets from '@/routes/targets'
import type { Conversation, DashboardCampaign, DashboardDiscoveryRun, DashboardReply, DashboardStats, LinkedinAccount, LinkedinPost, Mailbox, Recommendation, RedditReply } from '@/types'
import { CLASSIFICATIONS } from '@/types/inbox'

defineOptions({ layout: AppLayout })

const props = defineProps<{
    onboarding: boolean
    greeting: { name: string | null, days_running: number }
    stats: DashboardStats
    autonomy: {
        email: 'supervised' | 'semi_auto' | 'autonomous'
        linkedin: 'supervised' | 'autonomous'
    }
    newLeadsCount: number
    openRecommendations: Recommendation[]
    runningDiscoveryRun: DashboardDiscoveryRun | null
    campaigns: DashboardCampaign[]
    mailboxes: Mailbox[]
    latestReplies: DashboardReply[]
    review: {
        redditReplies: RedditReply[]
        linkedinPosts: LinkedinPost[]
        linkedinAccounts: LinkedinAccount[]
        conversations: Conversation[]
    }
}>()

const page = usePage()
const toast = useToast()

// Everything waiting on a person, whatever the channel. Each row opens the
// SAME component the channel's own page renders, so reviewing here and there
// is one experience. Held as a kind and an id, not the item itself, same as
// `Inbox.vue`: after an action the page reloads, the item leaves the list,
// and the modal closes on its own because the id no longer finds anything.
type ReviewKind = 'reddit' | 'linkedin' | 'email'

const redditThreads = computed(() => groupThreads(props.review.redditReplies))

const reviewItems = computed(() => [
    ...redditThreads.value.map(thread => ({
        kind: 'reddit' as ReviewKind,
        id: thread.permalink,
        icon: 'i-lucide-message-circle',
        label: 'Reddit reply',
        title: thread.threadTitle ?? 'Reddit thread',
        detail: `${thread.subreddit ? `r/${thread.subreddit} · ` : ''}${thread.replies.length} ${thread.replies.length === 1 ? 'draft' : 'drafts'}`
    })),
    ...props.review.linkedinPosts.map(post => ({
        kind: 'linkedin' as ReviewKind,
        id: String(post.id),
        icon: 'i-lucide-linkedin',
        label: 'LinkedIn post',
        title: post.body.split('\n')[0],
        detail: post.evidence
    })),
    ...props.review.conversations.map(conversation => ({
        kind: 'email' as ReviewKind,
        id: String(conversation.id),
        icon: 'i-lucide-mail',
        label: 'Email reply',
        title: conversation.lead.name ?? conversation.lead.email ?? 'Unknown',
        detail: conversation.messages.find(message => message.direction === 'inbound')?.body ?? ''
    }))
])

const reviewing = ref<{ kind: ReviewKind, id: string } | null>(null)

const reviewThread = computed(() => reviewing.value?.kind === 'reddit'
    ? redditThreads.value.find(thread => thread.permalink === reviewing.value!.id) ?? null
    : null)
const reviewPost = computed(() => reviewing.value?.kind === 'linkedin'
    ? props.review.linkedinPosts.find(post => String(post.id) === reviewing.value!.id) ?? null
    : null)
const reviewConversation = computed(() => reviewing.value?.kind === 'email'
    ? props.review.conversations.find(conversation => String(conversation.id) === reviewing.value!.id) ?? null
    : null)

const reviewOpen = computed({
    get: () => reviewThread.value !== null || reviewPost.value !== null || reviewConversation.value !== null,
    set: (value: boolean) => {
        if (!value) {
            reviewing.value = null
        }
    }
})

const decidingRecommendation = ref<string | null>(null)

function decideRecommendation (recommendation: Recommendation, status: 'done' | 'archived') {
    decidingRecommendation.value = recommendation.key
    router.put(recommendationRoutes.status.url({ project: page.props.currentProject!.slug, key: recommendation.key }), { status }, {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: status === 'done' ? 'Marked as done' : 'Dismissed', color: 'neutral' }),
        onFinish: () => decidingRecommendation.value = null
    })
}

function discussRecommendations () {
    openEvieChat('Let\'s discuss the acquisition ideas.')
}

const CAMPAIGN_STATUS: Record<string, { label: string, color: string }> = {
    active: { label: 'Sending', color: 'text-success' },
    paused: { label: 'Paused', color: 'text-warning' },
    draft: { label: 'Draft', color: 'text-dimmed' },
    completed: { label: 'Completed', color: 'text-muted' },
    archived: { label: 'Archived', color: 'text-dimmed' }
}

// One row per channel: the full explanation lives on the settings screen
// (`settings/Autonomy.vue`), this card only says where each one stands.
const AUTONOMY_LABEL = {
    supervised: 'Supervised',
    semi_auto: 'Semi-auto',
    autonomous: 'Autonomous'
}

const autonomyRows = computed(() => [
    { channel: 'Email', level: props.autonomy.email, notches: 3, index: ['supervised', 'semi_auto', 'autonomous'].indexOf(props.autonomy.email) },
    { channel: 'LinkedIn', level: props.autonomy.linkedin, notches: 2, index: ['supervised', 'autonomous'].indexOf(props.autonomy.linkedin) },
    { channel: 'Reddit', level: 'supervised' as const, notches: 1, index: 0 }
])

function verdict (classification: DashboardReply['classification']) {
    return classification ? CLASSIFICATIONS[classification] : null
}

function initials (name: string) {
    return name.trim().split(/\s+/).slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('')
}

function when (value: string | null) {
    return value === null ? '' : new Date(value).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
}

// The run has no live tick from the server, only a start time: a plain "since
// HH:MM" reads honestly, rather than a counter that implies a poll this page
// does not do.
function since (value: string | null) {
    return value === null ? '' : `since ${new Date(value).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}`
}

function mailboxCaption (mailbox: Mailbox) {
    if (mailbox.status !== 'active') {
        return mailbox.last_error ?? (mailbox.status === 'paused' ? 'Paused' : 'Cannot send')
    }

    return `${mailbox.sent_today} of ${mailbox.allowance_today} sent today`
}

const topupPercent = computed(() => {
    const wallet = page.props.wallet

    if (!wallet || !wallet.auto_topup_threshold) {
        return null
    }

    return Math.min(100, Math.round(wallet.balance / wallet.auto_topup_threshold * 100))
})
</script>

<template>
    <Head title="Dashboard" />

    <div class="p-6">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0">
                <h1 class="mb-1 text-xl font-semibold text-highlighted">
                    {{ greeting.name ? `Good to see you, ${greeting.name}` : 'Good to see you' }}
                </h1>
                <p class="text-sm text-muted">
                    {{ page.props.currentProject?.name }} has been running for
                    {{ greeting.days_running === 1 ? '1 day' : `${greeting.days_running} days` }}.
                </p>
            </div>

            <div class="flex shrink-0 gap-2">
                <UButton
                    :to="relativeUrl(campaignRoutes.index.url({ project: page.props.currentProject!.slug }))"
                    color="neutral"
                    variant="subtle"
                    label="New campaign"
                />
                <UButton
                    :to="relativeUrl(targets.index.url({ project: page.props.currentProject!.slug }))"
                    icon="i-lucide-radar"
                    label="Run discovery"
                />
            </div>
        </div>

        <UAlert
            v-if="onboarding"
            class="mb-4"
            color="primary"
            variant="subtle"
            icon="i-lucide-compass"
            title="Finish setting up"
            description="Your site has been read, or is being read. Agree with what it understood and the search starts. That is the whole setup."
            :actions="[{ label: 'Continue', to: relativeUrl(onboardingRoute.url({ project: page.props.currentProject!.slug })), color: 'primary', variant: 'solid' }]"
        />

        <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="grid min-w-0 gap-5">
                <!-- Found by discovery, at a company nobody has approved
                     yet: not sent to anyone until a person says yes to
                     the company. -->
                <div
                    v-if="newLeadsCount > 0"
                    class="flex flex-wrap items-start gap-3 rounded-lg bg-elevated p-4 ring ring-default"
                >
                    <UIcon
                        name="i-lucide-check-circle-2"
                        class="mt-0.5 size-4.5 shrink-0 text-primary"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-highlighted">
                            {{ newLeadsCount }} {{ newLeadsCount === 1 ? 'contact is' : 'contacts are' }} waiting for your approval
                        </p>
                        <p class="mt-0.5 text-sm text-muted">
                            Found by discovery. Nothing is sent to them until you say so.
                        </p>
                    </div>
                    <UButton
                        :to="relativeUrl(companies.index.url({ project: page.props.currentProject!.slug }, { query: { view: 'awaiting' } }))"
                        color="neutral"
                        variant="subtle"
                        size="sm"
                        label="Review them"
                    />
                </div>

                <UCard
                    v-if="reviewItems.length"
                    variant="subtle"
                    :ui="{ body: 'p-1.5 sm:p-1.5' }"
                >
                    <template #header>
                        <h3 class="text-sm font-semibold">
                            You have {{ reviewItems.length }} {{ reviewItems.length === 1 ? 'thing' : 'things' }} to review
                        </h3>
                    </template>

                    <button
                        v-for="item in reviewItems"
                        :key="`${item.kind}:${item.id}`"
                        type="button"
                        class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left hover:bg-elevated"
                        @click="reviewing = { kind: item.kind, id: item.id }"
                    >
                        <UIcon
                            :name="item.icon"
                            class="size-4 shrink-0 text-muted"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-highlighted">{{ item.title }}</span>
                            <span class="block truncate text-xs text-muted">{{ item.label }} · {{ item.detail }}</span>
                        </span>
                        <span class="shrink-0 text-xs font-medium text-primary">Review</span>
                    </button>
                </UCard>

                <!-- The stat strip: what the run has produced, not how far
                     people have got in a sequence - that funnel lives on
                     the campaign it belongs to. -->
                <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-default ring ring-default sm:grid-cols-4">
                    <div class="bg-elevated p-4">
                        <p class="mb-1.5 text-xs text-muted">
                            Companies found
                        </p>
                        <p class="font-mono text-2xl text-highlighted">
                            {{ stats.companies_found.toLocaleString() }}
                        </p>
                        <p class="mt-1 text-xs text-dimmed">
                            {{ stats.companies_kept.toLocaleString() }} kept after scoring
                        </p>
                    </div>
                    <div class="bg-elevated p-4">
                        <p class="mb-1.5 text-xs text-muted">
                            Emails sent
                        </p>
                        <p class="font-mono text-2xl text-highlighted">
                            {{ stats.sent.toLocaleString() }}
                        </p>
                    </div>
                    <div class="bg-elevated p-4">
                        <p class="mb-1.5 text-xs text-muted">
                            Replies
                        </p>
                        <p class="font-mono text-2xl text-highlighted">
                            {{ stats.replies.toLocaleString() }}
                        </p>
                        <p class="mt-1 text-xs text-dimmed">
                            {{ stats.positive_rate === null ? 'n/a' : `${stats.positive_rate}%` }} of sends
                        </p>
                    </div>
                    <div class="bg-elevated p-4">
                        <p class="mb-1.5 text-xs text-muted">
                            Interested
                        </p>
                        <p class="font-mono text-2xl text-success">
                            {{ stats.positive.toLocaleString() }}
                        </p>
                        <p class="mt-1 text-xs text-dimmed">
                            {{ stats.awaiting_human }} waiting on you
                        </p>
                    </div>
                </div>

                <!-- The one search spending budget right now, grouped
                     into the three stages the pipeline actually has. -->
                <UCard
                    v-if="runningDiscoveryRun"
                    variant="subtle"
                >
                    <template #header>
                        <div class="flex items-center gap-2.5">
                            <span class="size-1.5 shrink-0 rounded-full bg-success" />
                            <h3 class="min-w-0 truncate text-sm font-semibold">
                                Discovery running{{ runningDiscoveryRun.target_profile_name ? ` · ${runningDiscoveryRun.target_profile_name}` : '' }}
                            </h3>
                            <span class="ms-auto shrink-0 font-mono text-xs text-dimmed">{{ since(runningDiscoveryRun.started_at) }}</span>
                            <UButton
                                color="neutral"
                                variant="ghost"
                                size="xs"
                                label="Cancel"
                                @click="router.post(discoveryRuns.cancel.url({ project: page.props.currentProject!.slug, discovery_run: runningDiscoveryRun.id }), {}, { preserveScroll: true })"
                            />
                        </div>
                    </template>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div
                            v-for="stage in runningDiscoveryRun.stages"
                            :key="stage.label"
                            class="space-y-1.5"
                        >
                            <div class="h-[3px] overflow-hidden rounded-full bg-accented">
                                <div
                                    class="h-full rounded-full bg-primary transition-all"
                                    :class="stage.state === 'waiting' ? 'w-0' : ''"
                                    :style="stage.total ? `width:${Math.round(stage.done / stage.total * 100)}%` : (stage.state === 'done' ? 'width:100%' : '')"
                                />
                            </div>
                            <p
                                class="text-xs"
                                :class="stage.state === 'waiting' ? 'text-dimmed' : 'text-toned'"
                            >
                                {{ stage.label }}
                            </p>
                            <p class="font-mono text-xs text-dimmed">
                                {{ stage.state === 'waiting' ? 'waiting' : stage.state === 'done' ? 'done' : `${stage.done} / ${stage.total}` }}
                            </p>
                        </div>
                    </div>

                    <p class="mt-4 text-xs text-dimmed">
                        {{ runningDiscoveryRun.candidates_found }} candidate{{ runningDiscoveryRun.candidates_found === 1 ? '' : 's' }} found<template v-if="runningDiscoveryRun.max_companies">
                            , up to {{ runningDiscoveryRun.max_companies }}
                        </template>.
                        {{ runningDiscoveryRun.queries_used }}<template v-if="runningDiscoveryRun.max_queries">
                            / {{ runningDiscoveryRun.max_queries }}
                        </template> queries used.
                    </p>
                </UCard>

                <UCard
                    variant="subtle"
                    :ui="{ body: 'p-0 sm:p-0' }"
                >
                    <template #header>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold">
                                Campaigns
                            </h3>
                            <ULink
                                :href="relativeUrl(campaignRoutes.index.url({ project: page.props.currentProject!.slug }))"
                                class="ms-auto text-xs font-medium"
                            >All campaigns</ULink>
                        </div>
                    </template>

                    <p
                        v-if="!campaigns.length"
                        class="p-5 text-sm text-muted"
                    >
                        Nothing written yet.
                    </p>

                    <div
                        v-for="campaign in campaigns"
                        :key="campaign.id"
                    >
                        <ULink
                            :href="relativeUrl(campaignRoutes.show.url({ project: page.props.currentProject!.slug, campaign: campaign.id }))"
                            class="grid grid-cols-[minmax(0,1fr)_44px_44px_44px_74px] items-center gap-2.5 border-t border-default px-5 py-3 text-sm first:border-t-0"
                        >
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-highlighted">{{ campaign.name }}</span>
                                <span class="block text-xs text-dimmed">{{ campaign.steps_count }} step{{ campaign.steps_count === 1 ? '' : 's' }}</span>
                            </span>
                            <span class="text-right font-mono text-toned">{{ campaign.leads_count }}</span>
                            <span class="text-right font-mono text-toned">{{ campaign.sent_count }}</span>
                            <span class="text-right font-mono text-toned">{{ campaign.replies_count }}</span>
                            <span
                                class="flex items-center justify-end gap-1.5 text-xs"
                                :class="CAMPAIGN_STATUS[campaign.status]?.color"
                            >
                                <span
                                    class="size-1 rounded-full"
                                    :class="CAMPAIGN_STATUS[campaign.status]?.color.replace('text-', 'bg-')"
                                />
                                {{ CAMPAIGN_STATUS[campaign.status]?.label ?? campaign.status }}
                            </span>
                        </ULink>
                    </div>
                </UCard>
            </div>

            <div class="grid min-w-0 gap-4">
                <UCard variant="subtle">
                    <template #header>
                        <div class="flex items-baseline justify-between gap-2">
                            <h3 class="text-sm font-semibold">
                                Autonomy
                            </h3>
                            <ULink
                                :href="relativeUrl(autonomySettings.edit.url({ project: page.props.currentProject!.slug }))"
                                class="text-xs font-medium"
                            >Change</ULink>
                        </div>
                    </template>

                    <div class="space-y-3">
                        <div
                            v-for="row in autonomyRows"
                            :key="row.channel"
                        >
                            <div class="mb-1.5 flex items-baseline justify-between gap-2 text-sm">
                                <span class="text-muted">{{ row.channel }}</span>
                                <span class="font-medium text-highlighted">{{ AUTONOMY_LABEL[row.level] }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span
                                    v-for="notch in row.notches"
                                    :key="notch"
                                    class="h-[3px] flex-1 rounded-full"
                                    :class="notch - 1 <= row.index ? 'bg-primary' : 'bg-accented'"
                                />
                            </div>
                        </div>
                    </div>
                </UCard>

                <UCard
                    v-if="openRecommendations.length"
                    variant="subtle"
                >
                    <template #header>
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold">
                                Acquisition ideas
                            </h3>
                            <UButton
                                icon="i-lucide-sparkles"
                                color="neutral"
                                variant="ghost"
                                size="xs"
                                label="Discuss with Evie"
                                @click="discussRecommendations"
                            />
                        </div>
                    </template>

                    <div class="space-y-3">
                        <div
                            v-for="recommendation in openRecommendations"
                            :key="recommendation.key"
                            class="rounded-lg bg-elevated p-3"
                        >
                            <p class="font-medium text-highlighted">
                                {{ recommendation.idea }}
                            </p>
                            <p class="mt-1 text-xs text-muted">
                                {{ recommendation.evidence }}
                            </p>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <div class="flex shrink-0 gap-1.5">
                                    <UBadge
                                        :color="recommendation.impact === 'high' ? 'success' : recommendation.impact === 'medium' ? 'warning' : 'neutral'"
                                        variant="subtle"
                                        size="sm"
                                        :label="`${recommendation.impact} impact`"
                                    />
                                    <UBadge
                                        :color="recommendation.effort === 'low' ? 'success' : recommendation.effort === 'medium' ? 'warning' : 'neutral'"
                                        variant="subtle"
                                        size="sm"
                                        :label="`${recommendation.effort} effort`"
                                    />
                                </div>
                                <div class="flex shrink-0 gap-1.5">
                                    <UButton
                                        color="neutral"
                                        variant="ghost"
                                        size="xs"
                                        label="Reject"
                                        :loading="decidingRecommendation === recommendation.key"
                                        @click="decideRecommendation(recommendation, 'archived')"
                                    />
                                    <UButton
                                        color="primary"
                                        variant="ghost"
                                        size="xs"
                                        label="Done"
                                        :loading="decidingRecommendation === recommendation.key"
                                        @click="decideRecommendation(recommendation, 'done')"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </UCard>

                <UCard
                    v-if="mailboxes.length"
                    variant="subtle"
                    :ui="{ body: 'p-1.5 sm:p-1.5' }"
                >
                    <template #header>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold">
                                Mailboxes
                            </h3>
                            <ULink
                                :href="relativeUrl(mailboxSettings.index.url({ project: page.props.currentProject!.slug }))"
                                class="ms-auto text-xs font-medium"
                            >Manage</ULink>
                        </div>
                    </template>

                    <div
                        v-for="mailbox in mailboxes"
                        :key="mailbox.id"
                        class="flex items-center gap-2.5 rounded-lg px-2.5 py-2"
                    >
                        <span
                            class="size-1.5 shrink-0 rounded-full"
                            :class="mailbox.status === 'active' ? 'bg-success' : 'bg-error'"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-mono text-xs text-toned">{{ mailbox.from_email }}</span>
                            <span
                                class="block text-xs"
                                :class="mailbox.status === 'active' ? 'text-dimmed' : 'text-error'"
                            >{{ mailboxCaption(mailbox) }}</span>
                        </span>
                    </div>
                </UCard>

                <UCard
                    v-if="latestReplies.length"
                    variant="subtle"
                    :ui="{ body: 'p-1.5 sm:p-1.5' }"
                >
                    <template #header>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold">
                                Latest replies
                            </h3>
                            <ULink
                                :href="relativeUrl(inbox.url({ project: page.props.currentProject!.slug }))"
                                class="ms-auto text-xs font-medium"
                            >Inbox</ULink>
                        </div>
                    </template>

                    <ULink
                        v-for="reply in latestReplies"
                        :key="reply.id"
                        :href="relativeUrl(inbox.url({ project: page.props.currentProject!.slug }))"
                        class="flex items-start gap-2.5 rounded-lg px-2.5 py-2"
                    >
                        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-elevated text-[10px] font-semibold text-toned">
                            {{ initials(reply.lead.name) }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline gap-2">
                                <span class="min-w-0 truncate text-[13px] font-medium text-highlighted">{{ reply.lead.name }}</span>
                                <span class="ms-auto shrink-0 font-mono text-[10.5px] text-dimmed">{{ when(reply.at) }}</span>
                            </span>
                            <span class="block truncate text-xs text-muted">{{ reply.body }}</span>
                            <span
                                v-if="verdict(reply.classification)"
                                class="mt-0.5 block text-[11px]"
                                :class="`text-${verdict(reply.classification)!.color}`"
                            >{{ verdict(reply.classification)!.label }}</span>
                        </span>
                    </ULink>
                </UCard>

                <UCard
                    v-if="page.props.wallet"
                    variant="subtle"
                >
                    <template #header>
                        <div class="flex items-baseline justify-between gap-2">
                            <h3 class="text-sm font-semibold">
                                Credits
                            </h3>
                            <span class="font-mono text-sm text-highlighted">{{ page.props.wallet.balance.toLocaleString() }}</span>
                        </div>
                    </template>

                    <div
                        v-if="topupPercent !== null"
                        class="mb-3 h-[3px] overflow-hidden rounded-full bg-accented"
                    >
                        <div
                            class="h-full rounded-full bg-primary"
                            :style="`width:${topupPercent}%`"
                        />
                    </div>
                    <p
                        v-if="page.props.wallet.auto_topup_threshold"
                        class="mb-3 text-xs text-muted"
                    >
                        Auto top-up fires at {{ page.props.wallet.auto_topup_threshold.toLocaleString() }}.
                    </p>
                    <UButton
                        :to="relativeUrl(organizationBilling.edit.url({ project: page.props.currentProject!.slug }))"
                        color="neutral"
                        variant="subtle"
                        block
                        label="Top up"
                    />
                </UCard>
            </div>
        </div>
    </div>

    <UModal
        v-model:open="reviewOpen"
        :ui="{ overlay: 'z-50', content: 'z-50 max-w-6xl' }"
    >
        <template #content>
            <ConversationPanel
                v-if="reviewConversation"
                :conversation="reviewConversation"
                :sent="false"
                @close="reviewOpen = false"
            />
            <div
                v-else
                class="max-h-[85vh] overflow-y-auto p-4"
            >
                <RedditThreadCard
                    v-if="reviewThread"
                    :thread="reviewThread"
                />
                <LinkedinPostCard
                    v-else-if="reviewPost"
                    :post="reviewPost"
                    :linkedin-accounts="review.linkedinAccounts"
                />
            </div>
        </template>
    </UModal>
</template>
