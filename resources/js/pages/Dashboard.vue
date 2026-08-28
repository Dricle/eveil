<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import campaigns from '@/routes/campaigns'
import companies from '@/routes/companies'
import { inbox, onboarding as onboardingRoute } from '@/routes'
import type { DashboardStats, Pipeline } from '@/types'

const props = defineProps<{
    onboarding: boolean
    stats: DashboardStats
    pipeline: Pipeline
    recent: { id: number, agent: string, status: string, at: string | null }[]
}>()

const page = usePage()

// The funnel in the order the work actually goes, so a gap is visible as a gap.
const STAGES = [
    { key: 'pending', label: 'Waiting to start' },
    { key: 'running', label: 'In sequence' },
    { key: 'paused', label: 'Paused' },
    { key: 'completed', label: 'Finished' },
    { key: 'stopped', label: 'Stopped' },
    { key: 'failed', label: 'Failed' }
] as const

function stage (key: typeof STAGES[number]['key']) {
    return props.pipeline[key] ?? 0
}

function when (value: string | null) {
    return value === null ? '' : new Date(value).toLocaleString()
}
</script>

<template>
    <AppLayout>
        <Head title="Dashboard" />

        <div class="space-y-4 p-4">
            <h2 class="font-medium">
                {{ page.props.currentProject?.name }}
            </h2>

            <UAlert
                v-if="onboarding"
                color="primary"
                variant="subtle"
                icon="i-lucide-compass"
                title="Finish setting up"
                description="Your site has been read, or is being read. Agree with what it understood and the search starts. That is the whole setup."
                :actions="[{ label: 'Continue', to: onboardingRoute.url(), color: 'primary', variant: 'solid' }]"
            />

            <!-- The headline is the POSITIVE reply rate. A raw rate counts "no
                 thanks" and out-of-office as wins, and a dashboard that
                 flatters is worse than none. -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg p-4 ring ring-default">
                    <p class="text-sm text-muted">
                        Positive replies
                    </p>
                    <p class="text-2xl font-medium">
                        {{ stats.positive_rate === null ? 'n/a' : `${stats.positive_rate}%` }}
                    </p>
                    <p class="text-xs text-dimmed">
                        {{ stats.positive }} of {{ stats.replies }} replies, on {{ stats.sent }} sent
                    </p>
                </div>

                <ULink
                    :href="inbox.url()"
                    class="rounded-lg p-4 ring ring-default"
                >
                    <p class="text-sm text-muted">
                        Waiting for you
                    </p>
                    <p class="text-2xl font-medium">
                        {{ stats.awaiting_human }}
                    </p>
                    <p class="text-xs text-dimmed">
                        replies an agent left for a person to answer
                    </p>
                </ULink>

                <ULink
                    :href="companies.index.url()"
                    class="rounded-lg p-4 ring ring-default"
                >
                    <p class="text-sm text-muted">
                        Leads
                    </p>
                    <p class="text-2xl font-medium">
                        {{ stats.contacts }}
                    </p>
                    <p class="text-xs text-dimmed">
                        at {{ stats.companies }} companies still in the running
                    </p>
                </ULink>

                <ULink
                    :href="campaigns.index.url()"
                    class="rounded-lg p-4 ring ring-default"
                >
                    <p class="text-sm text-muted">
                        Active campaigns
                    </p>
                    <p class="text-2xl font-medium">
                        {{ stats.active_campaigns }}
                    </p>
                    <p class="text-xs text-dimmed">
                        sending within each mailbox's daily allowance
                    </p>
                </ULink>
            </div>

            <div class="grid gap-3 lg:grid-cols-2">
                <div class="space-y-2 rounded-lg p-4 ring ring-default">
                    <h3 class="font-medium">
                        Where the people are
                    </h3>

                    <div
                        v-for="item in STAGES"
                        :key="item.key"
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-muted">{{ item.label }}</span>
                        <span>{{ stage(item.key) }}</span>
                    </div>
                </div>

                <div class="space-y-2 rounded-lg p-4 ring ring-default">
                    <h3 class="font-medium">
                        What the agents have been doing
                    </h3>

                    <p
                        v-if="!recent.length"
                        class="text-sm text-muted"
                    >
                        Nothing yet.
                    </p>

                    <div
                        v-for="run in recent"
                        :key="run.id"
                        class="flex items-center justify-between gap-2 text-sm"
                    >
                        <span class="truncate">{{ run.agent }}</span>
                        <span class="text-dimmed">{{ run.status }} · {{ when(run.at) }}</span>
                    </div>

                    <!-- Self-hosted sees tokens: no provider reports a price,
                         so a figure in euros would be our own arithmetic
                         against a number that drifts. Cloud sees credits
                         spent instead - never a token count, never a model
                         name. -->
                    <p
                        v-if="'tokens_in' in stats"
                        class="border-t border-default pt-2 text-xs text-dimmed"
                    >
                        {{ stats.tokens_in.toLocaleString() }} tokens in ·
                        {{ stats.tokens_out.toLocaleString() }} out
                    </p>
                    <p
                        v-else
                        class="border-t border-default pt-2 text-xs text-dimmed"
                    >
                        {{ stats.credits_spent.toLocaleString() }} credits spent
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
