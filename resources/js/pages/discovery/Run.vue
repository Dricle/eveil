<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import TargetsLayout from '@/layouts/TargetsLayout.vue'
import discoveryRuns from '@/routes/discovery-runs'
import discoveryTasks from '@/routes/discovery-tasks'
import targets from '@/routes/targets'
import type { DiscoveryRun, DiscoveryTask } from '@/types'

const props = defineProps<{ run: DiscoveryRun }>()

const poll = usePoll(2000, { only: ['run'] }, { autoStart: props.run.running })

watch(() => props.run.running, running => running ? poll.start() : poll.stop())

const skipped = computed(() => (props.run.tasks ?? []).filter(task => task.status === 'skipped').length)

const KINDS = {
    plan: { icon: 'i-lucide-compass', label: 'Plan' },
    probe: { icon: 'i-lucide-search', label: 'Search' },
    harvest: { icon: 'i-lucide-list', label: 'Directory' },
    qualify: { icon: 'i-lucide-building-2', label: 'Company' }
}

const STATUS = {
    pending: { color: 'neutral' as const, label: 'Queued' },
    running: { color: 'primary' as const, label: 'Running' },
    succeeded: { color: 'success' as const, label: 'Done' },
    failed: { color: 'error' as const, label: 'Failed' },
    skipped: { color: 'neutral' as const, label: 'Skipped' }
}

// A short read on a run that came up short. Widening is deliberately NOT
// offered for a wrong profile: it would produce off-target leads the user then
// emails, and the complaints land on their own domain.
const DIAGNOSIS = {
    wrong_source: 'No candidate at all. The sources were wrong for this profile, not the profile itself.',
    bad_target_profile: 'Candidates were found but none fit. The profile is probably wrong, and widening it would only produce off-target leads.',
    too_narrow: 'Fewer companies than asked for. Either the profile is narrow, or this is the whole market.',
    no_contacts: 'Companies were qualified but no contact could be reached on them.'
}

/** What a node produced, in the few numbers worth reading in a list. */
function outcome (task: DiscoveryTask): string {
    const counts = [
        ['found', 'found'],
        ['candidates', 'queued'],
        ['harvested', 'harvested'],
        ['listings', 'directories'],
        ['pages', 'pages'],
        ['probes', 'probes']
    ] as const

    const parts = counts
        .filter(([key]) => typeof task.result[key] === 'number' && (task.result[key] as number) > 0)
        .map(([key, word]) => `${task.result[key]} ${word}`)

    if (task.kind === 'qualify' && task.status === 'succeeded') {
        parts.push(task.result.prospect ? 'kept' : 'not a prospect')
    }

    return parts.join(' · ')
}
</script>

<template>
    <TargetsLayout :current="run.profile_id">
        <Head :title="`Search: ${run.profile ?? 'run'}`" />

        <div class="max-w-3xl space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <ULink
                        v-if="run.profile_id"
                        :href="targets.searches.url(run.profile_id)"
                        class="text-sm text-muted"
                    >
                        ← Searches for {{ run.profile }}
                    </ULink>

                    <h2 class="mt-1 truncate font-medium">
                        Search of {{ new Date(run.started_at ?? '').toLocaleString() }}
                    </h2>
                </div>

                <UButton
                    v-if="run.running"
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-square"
                    label="Stop"
                    @click="router.post(discoveryRuns.cancel.url(run.id))"
                />
            </div>

            <UAlert
                v-if="run.error"
                color="error"
                variant="subtle"
                icon="i-lucide-triangle-alert"
                title="The search could not start"
                :description="run.error"
            />

            <UAlert
                v-else-if="run.diagnosis"
                color="warning"
                variant="subtle"
                icon="i-lucide-info"
                title="What this run found out"
                :description="DIAGNOSIS[run.diagnosis]"
            />

            <UAlert
                v-if="skipped"
                color="neutral"
                variant="subtle"
                icon="i-lucide-hand"
                title="This search stopped at its own ceiling"
                :description="`${skipped} step(s) were not run. One run is capped so a plan asking for eighty searches cannot spend eighty. Nothing was lost, and any step can be replayed on its own.`"
            />

            <UCard v-if="run.plan">
                <template #header>
                    <h3 class="text-sm font-medium">
                        Where it decided to look
                    </h3>
                </template>

                <p class="text-sm text-muted">
                    {{ run.plan }}
                </p>
            </UCard>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    v-for="line in [
                        { label: 'Queries', used: run.spent.queries, cap: run.budget.max_queries },
                        { label: 'Candidates', used: run.spent.candidates, cap: run.budget.max_companies },
                        { label: 'Pages read', used: run.spent.pages, cap: run.budget.max_pages },
                        { label: 'Companies kept', used: run.spent.qualified, cap: run.budget.max_qualified }
                    ]"
                    :key="line.label"
                    class="rounded-lg p-3 ring ring-default"
                >
                    <p class="text-sm text-muted">
                        {{ line.label }}
                    </p>
                    <p class="font-medium">
                        {{ line.used }}<span class="text-sm text-dimmed"> / {{ line.cap }}</span>
                    </p>
                </div>
            </div>

            <div class="space-y-2">
                <div
                    v-for="task in run.tasks ?? []"
                    :key="task.id"
                    class="flex items-start gap-3 rounded-lg p-3 ring ring-default"
                >
                    <UIcon
                        :name="KINDS[task.kind].icon"
                        :class="['mt-1 shrink-0 text-dimmed', task.status === 'running' && 'animate-pulse']"
                    />

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            {{ KINDS[task.kind].label }}
                            <span class="font-normal text-muted">{{ task.subject }}</span>
                        </p>

                        <p
                            v-if="outcome(task)"
                            class="text-sm text-muted"
                        >
                            {{ outcome(task) }}
                        </p>

                        <p
                            v-if="task.error"
                            class="text-sm text-error"
                        >
                            {{ task.error }}
                        </p>

                        <p
                            v-for="failure in task.result.failures ?? []"
                            :key="failure"
                            class="text-sm text-muted"
                        >
                            {{ failure }}
                        </p>

                        <p class="mt-0.5 text-xs text-dimmed">
                            <span v-if="task.duration_ms !== null">{{ Math.round(task.duration_ms / 100) / 10 }}s</span>
                            <span v-if="task.tokens"> · {{ task.tokens.toLocaleString() }} tokens</span>
                            <span v-if="task.attempts > 1"> · attempt {{ task.attempts }}</span>
                        </p>
                    </div>

                    <UBadge
                        :color="STATUS[task.status].color"
                        variant="subtle"
                        :label="STATUS[task.status].label"
                    />

                    <UButton
                        v-if="!task.status.match(/pending|running/)"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-rotate-ccw"
                        size="xs"
                        aria-label="Run this step again"
                        @click="router.post(discoveryTasks.replay.url(task.id))"
                    />
                </div>
            </div>
        </div>
    </TargetsLayout>
</template>
