<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
// Suffixed on purpose: an import named `campaigns` would shadow the prop of
// that name in every template expression, silently.
import campaignRoutes from '@/routes/campaigns'
import type { TargetProfile } from '@/types'

type Campaign = {
    id: number
    name: string
    status: string
    steps_count: number
    target_profile: { id: number | null, name: string | null, type: string | null } | null
}

// A resource collection arrives as a plain array: only a PAGINATED one is
// wrapped in `data`.
const props = defineProps<{
    campaigns: Campaign[]
    profiles: TargetProfile[]
    writing: boolean
    writingError: string | null
}>()

const profile = ref<number | undefined>(props.profiles[0]?.id)

// The sequence is written on the expensive model and takes a minute or two, so
// the page watches for it rather than leaving the user wondering.
const poll = usePoll(3000, { only: ['campaigns', 'writing', 'writingError'] }, { autoStart: props.writing })

watch(() => props.writing, busy => busy ? poll.start() : poll.stop())

const STATUS_COLORS: Record<string, 'neutral' | 'primary' | 'warning' | 'success'> = {
    draft: 'neutral',
    active: 'primary',
    paused: 'warning',
    completed: 'success'
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
                </div>
            </div>

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
                No active target profile yet — a sequence is written for a segment, so
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

                <UBadge
                    :color="STATUS_COLORS[campaign.status] ?? 'neutral'"
                    variant="subtle"
                    :label="campaign.status"
                />
            </div>
        </div>
    </AppLayout>
</template>
