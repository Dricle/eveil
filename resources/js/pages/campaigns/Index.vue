<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import CampaignSwitch from '@/components/CampaignSwitch.vue'
import AppLayout from '@/layouts/AppLayout.vue'
// Suffixed on purpose: an import named `campaigns` would shadow the prop of
// that name in every template expression, silently.
import campaignRoutes from '@/routes/campaigns'
import type { CampaignStatus, TargetProfile } from '@/types'

type Campaign = {
    id: number
    name: string
    status: CampaignStatus
    steps_count: number
    live_leads_count: number
    next_action_at: string | null
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
}>()

const profile = ref<number | undefined>(props.profiles[0]?.id)

// The sequence is written on the expensive model and takes a minute or two, so
// the page watches for it rather than leaving the user wondering.
const poll = usePoll(3000, { only: ['campaigns', 'uncovered', 'writing', 'writingError'] }, { autoStart: props.writing })

watch(() => props.writing, busy => busy ? poll.start() : poll.stop())

// The list is where the switch is thrown, so it also has to answer "and then
// what": how many people are in it, and when the next mail is owed.
//
// Empty first, whatever the status: "0 in sequence, next at …" is two facts
// that cannot both be true, and it is what a missing date used to print.
function due (campaign: Campaign): string {
    const people = `${campaign.live_leads_count} in sequence`

    if (campaign.live_leads_count === 0) {
        return 'Nobody in it yet'
    }

    if (campaign.status !== 'active') {
        return `${people}, on hold`
    }

    const at = campaign.next_action_at === null ? null : new Date(campaign.next_action_at)

    if (at === null || Number.isNaN(at.getTime())) {
        return `${people}, nothing owed right now`
    }

    return at.getTime() <= Date.now()
        ? `${people}, next one due now`
        : `${people}, next at ${at.toLocaleString()}`
}
</script>

<template>
    <AppLayout>
        <Head title="Campaigns" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="font-medium">
                        Campaigns
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
                        @click="router.post(campaignRoutes.generate.url(), { target_profile: profile })"
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
                        @click="router.post(campaignRoutes.generate.missing.url())"
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
                        @click="router.post(campaignRoutes.generate.missing.url())"
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

            <div
                v-for="campaign in campaigns"
                :key="campaign.id"
                class="flex flex-wrap items-center gap-3 rounded-lg p-4 ring ring-default"
            >
                <ULink
                    :href="campaignRoutes.show.url(campaign.id)"
                    class="min-w-0 flex-1 font-medium"
                >
                    {{ campaign.name }}
                </ULink>

                <UBadge
                    v-if="campaign.target_profile?.name"
                    color="neutral"
                    variant="subtle"
                    :label="campaign.target_profile.name"
                    :icon="campaign.target_profile.type === 'partner' ? 'i-lucide-handshake' : 'i-lucide-crosshair'"
                />

                <span class="text-sm text-dimmed">{{ campaign.steps_count }} steps</span>

                <span class="text-sm text-muted">{{ due(campaign) }}</span>

                <!-- Enrolment happens when the switch is thrown and on a
                     scheduled tick a supervised project never gets, so people
                     approved after the start need a way in. -->
                <UButton
                    v-if="campaign.status === 'active'"
                    icon="i-lucide-refresh-cw"
                    color="neutral"
                    variant="ghost"
                    label="Add people now"
                    @click="router.post(campaignRoutes.enrol.url(campaign.id), {}, { preserveScroll: true })"
                />

                <CampaignSwitch :campaign="campaign" />
            </div>
        </div>
    </AppLayout>
</template>
