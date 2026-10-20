<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { host, relativeUrl } from '@/lib/utils'
import { dashboard } from '@/routes'
import type { DashboardDiscoveryRun, DashboardStats } from '@/types'

defineOptions({ layout: AppLayout })

defineProps<{
    organization: string
    // Already sorted server-side: projects waiting on a person first.
    projects: {
        name: string
        slug: string
        url: string
        stats: DashboardStats
        week: DashboardStats
        waiting: number
        discovery: DashboardDiscoveryRun | null
    }[]
    totals: DashboardStats
    week: DashboardStats
}>()

// Credits on cloud, tokens on self-hosted: the server only ever sends one.
function aiUsage (stats: DashboardStats): string {
    return 'credits_spent' in stats
        ? `${stats.credits_spent.toLocaleString()} credits`
        : `${(stats.tokens_in + stats.tokens_out).toLocaleString()} tokens`
}

function thisWeek (count: number): string {
    return count === 0 ? 'none this week' : `+${count.toLocaleString()} this week`
}

// The stage a running search is in now: the first one not finished yet.
function currentStage (run: DashboardDiscoveryRun): string {
    return run.stages.find(stage => stage.state !== 'done')?.label ?? 'Finishing'
}

function projectDashboard (slug: string): string {
    return relativeUrl(dashboard.url({ project: slug }))
}
</script>

<template>
    <Head :title="`${organization} overview`" />

    <div class="p-6">
        <div class="mb-6">
            <h1 class="mb-1 text-xl font-semibold text-highlighted">
                All projects
            </h1>
            <p class="text-sm text-muted">
                {{ organization }}, every project you can see. The ones waiting on you come first.
            </p>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-default ring ring-default sm:grid-cols-4">
            <div class="bg-elevated p-4">
                <p class="mb-1.5 text-xs text-muted">
                    Companies found
                </p>
                <p class="font-mono text-2xl text-highlighted">
                    {{ totals.companies_found.toLocaleString() }}
                </p>
                <p class="mt-1 text-xs text-dimmed">
                    {{ thisWeek(week.companies_found) }}
                </p>
            </div>
            <div class="bg-elevated p-4">
                <p class="mb-1.5 text-xs text-muted">
                    Emails sent
                </p>
                <p class="font-mono text-2xl text-highlighted">
                    {{ totals.sent.toLocaleString() }}
                </p>
                <p class="mt-1 text-xs text-dimmed">
                    {{ thisWeek(week.sent) }}
                </p>
            </div>
            <div class="bg-elevated p-4">
                <p class="mb-1.5 text-xs text-muted">
                    Replies
                </p>
                <p class="font-mono text-2xl text-highlighted">
                    {{ totals.replies.toLocaleString() }}
                </p>
                <p class="mt-1 text-xs text-dimmed">
                    {{ thisWeek(week.replies) }}
                </p>
            </div>
            <div class="bg-elevated p-4">
                <p class="mb-1.5 text-xs text-muted">
                    Interested
                </p>
                <p class="font-mono text-2xl text-success">
                    {{ totals.positive.toLocaleString() }}
                </p>
                <p class="mt-1 text-xs text-dimmed">
                    {{ totals.positive_rate === null ? 'n/a' : `${totals.positive_rate}%` }} of sends · {{ thisWeek(week.positive) }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <UCard
                v-for="project in projects"
                :key="project.slug"
                variant="subtle"
            >
                <template #header>
                    <div class="flex items-center gap-2.5">
                        <span class="grid size-7 shrink-0 place-items-center rounded-md bg-primary text-xs font-semibold text-inverted">
                            {{ project.name.charAt(0).toUpperCase() }}
                        </span>
                        <div class="min-w-0">
                            <Link
                                :href="projectDashboard(project.slug)"
                                class="block truncate text-sm font-semibold text-highlighted hover:text-primary"
                            >
                                {{ project.name }}
                            </Link>
                            <p class="truncate text-xs text-muted">
                                {{ host(project.url) }}
                            </p>
                        </div>
                        <UButton
                            v-if="project.waiting > 0"
                            :to="projectDashboard(project.slug)"
                            class="ms-auto shrink-0"
                            color="warning"
                            variant="subtle"
                            size="xs"
                            trailing-icon="i-lucide-arrow-right"
                            :label="`${project.waiting} waiting on you`"
                        />
                        <span
                            v-else
                            class="ms-auto inline-flex shrink-0 items-center gap-1.5 text-xs text-muted"
                        >
                            <UIcon
                                name="i-lucide-check"
                                class="size-3.5 text-success"
                            />
                            All clear
                        </span>
                    </div>
                </template>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div>
                        <p class="text-xs text-muted">
                            Found
                        </p>
                        <p class="font-mono text-lg text-highlighted">
                            {{ project.stats.companies_found.toLocaleString() }}
                        </p>
                        <p class="text-xs text-dimmed">
                            {{ thisWeek(project.week.companies_found) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted">
                            Sent
                        </p>
                        <p class="font-mono text-lg text-highlighted">
                            {{ project.stats.sent.toLocaleString() }}
                        </p>
                        <p class="text-xs text-dimmed">
                            {{ thisWeek(project.week.sent) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted">
                            Replies
                        </p>
                        <p class="font-mono text-lg text-highlighted">
                            {{ project.stats.replies.toLocaleString() }}
                        </p>
                        <p class="text-xs text-dimmed">
                            {{ thisWeek(project.week.replies) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted">
                            Interested
                        </p>
                        <p class="font-mono text-lg text-success">
                            {{ project.stats.positive.toLocaleString() }}
                        </p>
                        <p class="text-xs text-dimmed">
                            {{ project.stats.positive_rate === null ? 'n/a' : `${project.stats.positive_rate}% of sends` }}
                        </p>
                    </div>
                </div>

                <template #footer>
                    <div class="flex items-center gap-2 text-xs text-muted">
                        <template v-if="project.discovery">
                            <span class="size-1.5 shrink-0 rounded-full bg-success" />
                            <span class="min-w-0 truncate">
                                Discovery running{{ project.discovery.target_profile_name ? ` · ${project.discovery.target_profile_name}` : '' }} · {{ currentStage(project.discovery) }}
                            </span>
                        </template>
                        <span v-else>No discovery running</span>
                        <span class="ms-auto shrink-0 font-mono text-dimmed">{{ aiUsage(project.stats) }}</span>
                    </div>
                </template>
            </UCard>
        </div>
    </div>
</template>
