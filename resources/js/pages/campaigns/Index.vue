<script setup lang="ts">
import { Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import CampaignSwitch from '@/components/CampaignSwitch.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { relativeUrl } from '@/lib/utils'
// Suffixed on purpose: an import named `campaigns` would shadow the prop of
// that name in every template expression, silently.
import campaignRoutes from '@/routes/campaigns'
import type { CampaignStatus, TargetProfile } from '@/types'

defineOptions({ layout: AppLayout })

const page = usePage()

type Campaign = {
    id: number
    name: string
    status: CampaignStatus
    steps_count: number
    live_leads_count: number
    sent_count: number
    replies_count: number
    next_action_at: string | null
    updated_at: string
    target_profile: { id: number | null, name: string | null, type: string | null } | null
}

// A resource collection arrives as a plain array: only a PAGINATED one is
// wrapped in `data`.
const props = defineProps<{
    campaigns: Campaign[]
    profiles: TargetProfile[]
    uncovered: { id: number, name: string }[]
    writing: boolean
    writingError: string | null
    stats: { sent_this_week: number, replies: number, positive: number, awaiting: number }
    autonomyLevel: 'supervised' | 'semi_auto' | 'autonomous'
}>()

const profile = ref<number | undefined>(props.profiles[0]?.id)

// Same vocabulary as the dashboard's own campaign list (`Dashboard.vue`'s
// `CAMPAIGN_STATUS`), kept in sync by hand so a status reads the same word
// wherever it shows up.
const STATUS_META: Record<CampaignStatus, { label: string, color: 'success' | 'warning' | 'neutral' }> = {
    draft: { label: 'Draft', color: 'neutral' },
    active: { label: 'Sending', color: 'success' },
    paused: { label: 'Paused', color: 'warning' },
    completed: { label: 'Completed', color: 'neutral' },
    archived: { label: 'Archived', color: 'neutral' }
}

const inSequence = computed(() => props.campaigns.reduce((sum, campaign) => sum + campaign.live_leads_count, 0))

// A folder per status, the way the inbox filters by folder: only statuses
// actually present get a chip, so a fresh project never shows four empty ones.
const filter = ref<CampaignStatus | 'all'>('all')

const statusCounts = computed(() => {
    const counts = {} as Record<CampaignStatus, number>

    for (const campaign of props.campaigns) {
        counts[campaign.status] = (counts[campaign.status] ?? 0) + 1
    }

    return counts
})

const visibleStatuses = computed(() => (Object.keys(statusCounts.value) as CampaignStatus[])
    .sort((a, b) => a.localeCompare(b)))

const filteredCampaigns = computed(() => filter.value === 'all'
    ? props.campaigns
    : props.campaigns.filter(campaign => campaign.status === filter.value))

// The sequence is written on the expensive model and takes a minute or two, so
// the page watches for it rather than leaving the user wondering.
const poll = usePoll(3000, { only: ['campaigns', 'uncovered', 'writing', 'writingError'] }, { autoStart: props.writing })

watch(() => props.writing, busy => busy ? poll.start() : poll.stop())

// When the next mail is owed, for an active campaign only - everything else
// has no send to time, and shows when it was last touched instead.
function timing (campaign: Campaign): string | null {
    if (campaign.status !== 'active') {
        return null
    }

    const at = campaign.next_action_at === null ? null : new Date(campaign.next_action_at)

    if (at === null || Number.isNaN(at.getTime())) {
        return 'Nothing owed right now'
    }

    return at.getTime() <= Date.now() ? 'Next send due now' : `Next send at ${at.toLocaleString()}`
}
</script>

<template>
    <Head title="Email Campaigns" />

    <div class="space-y-6 p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-medium">
                    Email Campaigns
                </h2>
                <p class="text-sm text-muted">
                    The agent writes the sequence from your product and the segment
                    it is aimed at. Nothing sends until you activate it.
                </p>
            </div>

            <div class="flex items-end gap-2">
                <USelect
                    v-model="profile"
                    :items="profiles.map(p => ({ label: p.name, value: p.id }))"
                    placeholder="For which segment"
                    class="w-64"
                />

                <UButton
                    icon="i-lucide-sparkles"
                    :loading="writing"
                    :disabled="writing || !profile"
                    :label="writing ? 'Writing…' : 'Write a sequence'"
                    @click="router.post(campaignRoutes.generate.url({ project: page.props.currentProject!.slug }), { target_profile: profile })"
                />

                <!-- One click for every segment that has none. Doing it one
                     at a time is work the app should be doing. -->
                <UButton
                    v-if="uncovered.length"
                    icon="i-lucide-layers"
                    color="neutral"
                    variant="subtle"
                    :disabled="writing"
                    :label="`Write the ${uncovered.length} missing`"
                    @click="router.post(campaignRoutes.generate.missing.url({ project: page.props.currentProject!.slug }))"
                />
            </div>
        </div>

        <!-- A segment with no sequence does not appear on a list of
             sequences, so nothing else on this page can point at it. -->
        <UAlert
            v-if="uncovered.length"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            :title="uncovered.length === 1
                ? 'One segment has no sequence'
                : `${uncovered.length} segments have no sequence`"
            :description="`Nothing is ever written to ${uncovered.map(item => item.name).join(', ')}. The searches keep finding companies for them, and none of those companies can be mailed until a sequence exists.`"
        >
            <template #actions>
                <UButton
                    color="warning"
                    variant="solid"
                    icon="i-lucide-sparkles"
                    :loading="writing"
                    :disabled="writing"
                    :label="writing ? 'Writing…' : 'Write them now'"
                    @click="router.post(campaignRoutes.generate.missing.url({ project: page.props.currentProject!.slug }))"
                />
            </template>
        </UAlert>

        <UAlert
            v-if="writingError"
            color="error"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="The last sequence was not written"
            :description="writingError"
        />

        <p
            v-if="!profiles.length"
            class="text-sm text-muted"
        >
            No active target profile yet. A sequence is written for a segment, so
            start in Targets.
        </p>

        <div
            v-if="!campaigns.length"
            class="rounded-lg p-6 text-sm text-muted ring ring-default"
        >
            Nothing written yet.
        </div>

        <template v-else>
            <!-- What the sequences have produced, not how far people have
                 got in any one of them - that funnel lives on the campaign
                 it belongs to. Same shape as the dashboard's own strip. -->
            <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-default ring ring-default sm:grid-cols-5">
                <div class="bg-elevated p-4">
                    <p class="mb-1.5 text-xs text-muted">
                        In sequence
                    </p>
                    <p class="font-mono text-2xl text-highlighted">
                        {{ inSequence.toLocaleString() }}
                    </p>
                </div>
                <div class="bg-elevated p-4">
                    <p class="mb-1.5 text-xs text-muted">
                        Sent this week
                    </p>
                    <p class="font-mono text-2xl text-highlighted">
                        {{ stats.sent_this_week.toLocaleString() }}
                    </p>
                </div>
                <div class="bg-elevated p-4">
                    <p class="mb-1.5 text-xs text-muted">
                        Replies
                    </p>
                    <p class="font-mono text-2xl text-highlighted">
                        {{ stats.replies.toLocaleString() }}
                    </p>
                </div>
                <div class="bg-elevated p-4">
                    <p class="mb-1.5 text-xs text-muted">
                        Positive
                    </p>
                    <p class="font-mono text-2xl text-success">
                        {{ stats.positive.toLocaleString() }}
                    </p>
                </div>
                <div class="bg-elevated p-4">
                    <p class="mb-1.5 text-xs text-muted">
                        Awaiting you
                    </p>
                    <p class="font-mono text-2xl text-warning">
                        {{ stats.awaiting.toLocaleString() }}
                    </p>
                </div>
            </div>

            <!-- A folder per status, same pattern as the inbox's own filter
                 row: only statuses actually present get a chip. -->
            <div class="flex flex-wrap items-center gap-2">
                <UButton
                    size="sm"
                    :color="filter === 'all' ? 'primary' : 'neutral'"
                    :variant="filter === 'all' ? 'subtle' : 'outline'"
                    class="rounded-full"
                    @click="filter = 'all'"
                >
                    All
                    <span class="text-xs opacity-60">{{ campaigns.length }}</span>
                </UButton>
                <UButton
                    v-for="status in visibleStatuses"
                    :key="status"
                    size="sm"
                    :color="filter === status ? 'primary' : 'neutral'"
                    :variant="filter === status ? 'subtle' : 'outline'"
                    class="rounded-full"
                    @click="filter = status"
                >
                    {{ STATUS_META[status].label }}
                    <span class="text-xs opacity-60">{{ statusCounts[status] }}</span>
                </UButton>
            </div>

            <div class="grid gap-3">
                <article
                    v-for="campaign in filteredCampaigns"
                    :key="campaign.id"
                    class="rounded-lg p-4 ring ring-default"
                >
                    <!-- Row 1: status, name, and whatever time is most
                         relevant right now - the next send for an active
                         campaign, when it was last touched otherwise. -->
                    <div class="mb-1.5 flex flex-wrap items-baseline gap-2.5">
                        <UBadge
                            :color="STATUS_META[campaign.status].color"
                            variant="subtle"
                            class="rounded-full"
                        >
                            <span
                                v-if="campaign.status === 'active'"
                                class="mr-1 inline-block size-1.5 rounded-full bg-current"
                            />
                            {{ STATUS_META[campaign.status].label }}
                        </UBadge>

                        <ULink
                            :href="relativeUrl(campaignRoutes.show.url({ project: page.props.currentProject!.slug, campaign: campaign.id }))"
                            class="min-w-0 font-semibold text-highlighted"
                        >
                            {{ campaign.name }}
                        </ULink>

                        <span class="ml-auto shrink-0 text-xs text-dimmed">
                            {{ timing(campaign) ?? `Updated ${new Date(campaign.updated_at).toLocaleString()}` }}
                        </span>
                    </div>

                    <!-- Row 2: the segment this sequence targets, and where
                         it stands - a plain line, not a badge, so it reads
                         alongside the count rather than competing with the
                         status pill above it. -->
                    <div class="mb-3 flex flex-wrap items-center gap-1.5 text-xs text-muted">
                        <span
                            v-if="campaign.target_profile?.name"
                            class="inline-flex min-w-0 items-center gap-1.5"
                        >
                            <UIcon
                                :name="campaign.target_profile.type === 'partner' ? 'i-lucide-handshake' : 'i-lucide-crosshair'"
                                class="size-3.5 shrink-0 opacity-70"
                            />
                            <span class="truncate">{{ campaign.target_profile.name }}</span>
                        </span>
                        <span
                            v-if="campaign.target_profile?.name"
                            class="opacity-40"
                        >·</span>
                        <span><span class="font-mono text-toned">{{ campaign.live_leads_count }}</span> in sequence</span>
                        <span class="opacity-40">·</span>
                        <span>{{ campaign.steps_count }} steps</span>
                    </div>

                    <!-- Row 3: what happened, and the one thing to do about
                         it - a draft has nothing to report yet, so it gets a
                         plain warning instead of a zero. -->
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span
                            v-if="campaign.status === 'draft'"
                            class="text-warning"
                        >
                            Nothing has been sent yet. Read it before you switch it on.
                        </span>
                        <template v-else>
                            <span class="text-muted"><span class="font-mono text-toned">{{ campaign.sent_count }}</span> sent</span>
                            <span class="text-dimmed opacity-40">·</span>
                            <span class="text-muted"><span class="font-mono text-toned">{{ campaign.replies_count }}</span> replies</span>
                        </template>

                        <span class="ml-auto flex flex-wrap items-center gap-2">
                            <!-- `eveil:enrol-due` already adds newly-approved
                                 people to every active campaign on its own
                                 tick, EXCEPT on a supervised project: there
                                 the user decides when, on purpose, so that is
                                 the only case this needs a manual way in. -->
                            <UButton
                                v-if="campaign.status === 'active' && autonomyLevel === 'supervised'"
                                icon="i-lucide-refresh-cw"
                                color="neutral"
                                size="sm"
                                variant="ghost"
                                label="Add approved people now"
                                @click="router.post(campaignRoutes.enrol.url({ project: page.props.currentProject!.slug, campaign: campaign.id }), {}, { preserveScroll: true })"
                            />

                            <CampaignSwitch :campaign="campaign" />
                        </span>
                    </div>
                </article>
            </div>
        </template>
    </div>
</template>
