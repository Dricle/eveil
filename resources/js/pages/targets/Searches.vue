<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import TargetHeader from '@/components/TargetHeader.vue'
import TargetsLayout from '@/layouts/TargetsLayout.vue'
import discoveryRuns from '@/routes/discovery-runs'
import type { DiscoveryRun, TargetProfile } from '@/types'

const props = defineProps<{
    profile: TargetProfile
    runs: DiscoveryRun[]
}>()

const working = computed(() => props.runs.some(run => run.running))

const poll = usePoll(4000, { only: ['runs'] }, { autoStart: working.value })

watch(working, busy => busy ? poll.start() : poll.stop())

const STATUS = {
    pending: { color: 'neutral' as const, label: 'Queued' },
    planning: { color: 'neutral' as const, label: 'Planning' },
    running: { color: 'primary' as const, label: 'Running' },
    succeeded: { color: 'success' as const, label: 'Done' },
    exhausted: { color: 'warning' as const, label: 'Came up short' },
    aborted: { color: 'neutral' as const, label: 'Stopped' },
    failed: { color: 'error' as const, label: 'Failed' }
}

function status (run: DiscoveryRun) {
    return STATUS[run.status as keyof typeof STATUS] ?? { color: 'neutral' as const, label: run.status }
}
</script>

<template>
    <TargetsLayout :current="profile.id">
        <Head :title="`Searches — ${profile.name}`" />

        <div class="max-w-3xl space-y-4">
            <TargetHeader
                :profile="profile"
                tab="searches"
            />

            <div class="flex items-start justify-between gap-4">
                <p class="flex-1 text-sm text-muted">
                    Every time this profile was put to the map, to the web and to
                    the directories they turn up.
                </p>

                <UButton
                    icon="i-lucide-radar"
                    color="neutral"
                    variant="subtle"
                    label="New search"
                    @click="router.post(discoveryRuns.store.url(), { target_profile: profile.id })"
                />
            </div>

            <p
                v-if="!runs.length"
                class="text-sm text-muted"
            >
                Never searched. The agent works out where to look before it
                spends anything.
            </p>

            <ULink
                v-for="run in runs"
                :key="run.id"
                :href="discoveryRuns.show.url(run.id)"
                class="flex items-center gap-3 rounded-lg p-4 ring ring-default hover:bg-elevated/50"
            >
                <UIcon
                    :name="run.running ? 'i-lucide-loader' : 'i-lucide-radar'"
                    :class="['shrink-0 text-dimmed', run.running && 'animate-spin']"
                />

                <div class="min-w-0 flex-1">
                    <p class="text-sm">
                        {{ run.spent.candidates }} found ·
                        {{ run.spent.qualified }} kept ·
                        {{ run.spent.pages }} pages read
                    </p>
                    <p class="text-sm text-dimmed">
                        {{ new Date(run.started_at ?? '').toLocaleString() }}
                    </p>
                </div>

                <UBadge
                    :color="status(run).color"
                    variant="subtle"
                    :label="status(run).label"
                />
            </ULink>
        </div>
    </TargetsLayout>
</template>
