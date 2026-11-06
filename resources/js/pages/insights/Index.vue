<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { relativeUrl } from '@/lib/utils'
import insightRoutes from '@/routes/insights'
import ideaRoutes from '@/routes/seo/ideas'
import knowledgeBaseRoutes from '@/routes/settings/knowledge-base'
import type { Insight, InsightKind, Paginated } from '@/types'

defineOptions({ layout: AppLayout })

const props = defineProps<{
    insights: Paginated<Insight>
    filters: { kind: InsightKind | 'all', competitor: string, unread: boolean }
    competitors: string[]
    unreadCount: number
}>()

const page = usePage()
const toast = useToast()

const KINDS = [
    { label: 'Everything', value: 'all' },
    { label: 'Competitor mentions', value: 'competitor_mention' },
    { label: 'Missing features', value: 'feature' },
    { label: 'Acquisition ideas', value: 'acquisition' },
    { label: 'Article ideas', value: 'article' }
]

const KIND_BADGE: Record<InsightKind, { label: string, icon: string }> = {
    competitor_mention: { label: 'Competitor', icon: 'i-lucide-message-square-quote' },
    feature: { label: 'Missing feature', icon: 'i-lucide-puzzle' },
    acquisition: { label: 'Acquisition', icon: 'i-lucide-trending-up' },
    article: { label: 'Article idea', icon: 'i-lucide-lightbulb' }
}

const SIGNAL_COLOR = {
    complaint: 'error',
    switching: 'warning',
    comparison: 'info',
    praise: 'success'
} as const

const LEVEL_COLOR = { high: 'success', medium: 'warning', low: 'neutral' } as const

const kind = ref(props.filters.kind)
const competitor = ref(props.filters.competitor)
const unread = ref(props.filters.unread)

function reload (extra: Record<string, unknown> = {}) {
    router.get(insightRoutes.index.url({ project: page.props.currentProject!.slug }), {
        kind: kind.value === 'all' ? undefined : kind.value,
        competitor: competitor.value === 'all' ? undefined : competitor.value,
        unread: unread.value ? 1 : undefined,
        ...extra
    }, { preserveState: true, preserveScroll: true, replace: true })
}

watch([kind, competitor, unread], () => reload())

// Held per item: several can be clicked in a row while the first one's
// request is still in flight.
const busy = ref<number | null>(null)

function update (insight: Insight, data: { read?: boolean, status?: 'used' | 'dismissed' }, toastTitle?: string) {
    busy.value = insight.id
    router.put(insightRoutes.update.url({ project: page.props.currentProject!.slug, insight: insight.id }), data, {
        preserveScroll: true,
        onSuccess: () => toastTitle && toast.add({ title: toastTitle, color: 'neutral' }),
        onFinish: () => busy.value = null
    })
}

// Following the link is reading it.
function open (insight: Insight) {
    if (!insight.read_at) {
        router.put(insightRoutes.update.url({ project: page.props.currentProject!.slug, insight: insight.id }), { read: true }, { preserveScroll: true })
    }
}

function writeArticle (insight: Insight) {
    busy.value = insight.id
    router.post(ideaRoutes.write.url({ project: page.props.currentProject!.slug, idea: insight.id }), {}, {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Writing that article', description: 'It will appear in the SEO drafts in a few minutes.', color: 'success' }),
        onFinish: () => busy.value = null
    })
}

const markingAll = ref(false)

function markAllRead () {
    markingAll.value = true
    router.post(insightRoutes.read.url({ project: page.props.currentProject!.slug }), {}, {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Everything marked as read', color: 'neutral' }),
        onFinish: () => markingAll.value = false
    })
}
</script>

<template>
    <Head title="Insights" />

    <div class="space-y-4 p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-medium">
                    Insights
                </h2>
                <p class="text-sm text-muted">
                    What Eveil noticed while reading: what people say about your
                    competitors on Reddit, features they have and you lack, ways to
                    bring more people to your site, and articles worth writing.
                </p>
            </div>

            <UButton
                icon="i-lucide-check-check"
                color="neutral"
                variant="subtle"
                :label="unreadCount ? `Mark all read (${unreadCount})` : 'All read'"
                :disabled="!unreadCount"
                :loading="markingAll"
                @click="markAllRead"
            />
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <USelect
                v-model="kind"
                :items="KINDS"
                class="w-52"
            />
            <USelect
                v-if="competitors.length"
                v-model="competitor"
                :items="[{ label: 'Every competitor', value: 'all' }, ...competitors.map(name => ({ label: name, value: name }))]"
                class="w-52"
            />
            <USwitch
                v-model="unread"
                label="Unread only"
            />
        </div>

        <UAlert
            v-if="!competitors.length"
            color="neutral"
            variant="subtle"
            icon="i-lucide-info"
            title="No competitors named yet"
            description="Name your competitors in the knowledge base and Eveil will watch what people say about them on Reddit, and compare their features with yours every week."
            :actions="[{ label: 'Open the knowledge base', color: 'neutral', variant: 'outline', to: relativeUrl(knowledgeBaseRoutes.edit.url({ project: page.props.currentProject!.slug })) }]"
        />

        <div
            v-if="insights.data.length"
            class="divide-y divide-default rounded-lg ring ring-default"
        >
            <div
                v-for="insight in insights.data"
                :key="insight.id"
                class="flex flex-wrap items-start gap-3 px-4 py-3"
                :class="insight.read_at ? '' : 'bg-elevated/50'"
            >
                <span
                    class="mt-1.5 size-2 shrink-0 rounded-full"
                    :class="insight.read_at ? 'bg-transparent' : 'bg-primary'"
                />

                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <UBadge
                            :icon="KIND_BADGE[insight.kind].icon"
                            :label="KIND_BADGE[insight.kind].label"
                            color="neutral"
                            variant="subtle"
                            size="sm"
                        />
                        <UBadge
                            v-if="insight.details.competitor"
                            :label="insight.details.competitor"
                            color="neutral"
                            variant="outline"
                            size="sm"
                        />
                        <UBadge
                            v-if="insight.details.signal"
                            :label="insight.details.signal"
                            :color="SIGNAL_COLOR[insight.details.signal]"
                            variant="subtle"
                            size="sm"
                        />
                        <UBadge
                            v-if="insight.details.impact"
                            :label="`${insight.details.impact} impact`"
                            :color="LEVEL_COLOR[insight.details.impact]"
                            variant="subtle"
                            size="sm"
                        />
                        <UBadge
                            v-if="insight.details.effort"
                            :label="`${insight.details.effort} effort`"
                            :color="insight.details.effort === 'low' ? 'success' : insight.details.effort === 'medium' ? 'warning' : 'neutral'"
                            variant="subtle"
                            size="sm"
                        />
                        <span
                            v-if="insight.created_at"
                            class="text-xs text-dimmed"
                        >{{ new Date(insight.created_at).toLocaleDateString() }}</span>
                    </div>

                    <p
                        class="text-sm text-highlighted"
                        :class="insight.read_at ? '' : 'font-medium'"
                    >
                        {{ insight.kind === 'acquisition' || insight.kind === 'feature' ? insight.title : insight.angle }}
                    </p>
                    <p
                        v-if="insight.kind === 'acquisition' || insight.kind === 'feature'"
                        class="text-xs text-muted"
                    >
                        {{ insight.angle }}
                    </p>
                    <a
                        v-if="insight.url"
                        :href="insight.url"
                        target="_blank"
                        rel="noopener"
                        class="text-xs text-primary"
                        @click="open(insight)"
                    >{{ insight.details.subreddit ? `r/${insight.details.subreddit}: ` : '' }}{{ insight.details.thread_title ?? insight.title }} ↗</a>
                </div>

                <div class="flex shrink-0 gap-1.5">
                    <UButton
                        :icon="insight.read_at ? 'i-lucide-mail' : 'i-lucide-mail-open'"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        :aria-label="insight.read_at ? 'Mark unread' : 'Mark read'"
                        :loading="busy === insight.id"
                        @click="update(insight, { read: !insight.read_at })"
                    />
                    <UButton
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        label="Dismiss"
                        :loading="busy === insight.id"
                        @click="update(insight, { status: 'dismissed' }, 'Dismissed')"
                    />
                    <UButton
                        v-if="insight.kind === 'acquisition' || insight.kind === 'feature'"
                        icon="i-lucide-check"
                        color="primary"
                        variant="subtle"
                        size="xs"
                        label="Done"
                        :loading="busy === insight.id"
                        @click="update(insight, { status: 'used' }, 'Marked as done')"
                    />
                    <UButton
                        v-if="insight.kind === 'article'"
                        icon="i-lucide-pen-line"
                        color="primary"
                        variant="subtle"
                        size="xs"
                        label="Write it"
                        :loading="busy === insight.id"
                        @click="writeArticle(insight)"
                    />
                </div>
            </div>
        </div>

        <p
            v-else
            class="rounded-lg p-6 text-sm text-muted ring ring-default"
        >
            {{ filters.unread ? 'Nothing unread.' : 'Nothing here yet. Insights appear as Eveil scans Reddit and reads your competitors.' }}
        </p>

        <div
            v-if="insights.meta.last_page > 1"
            class="flex justify-end"
        >
            <UPagination
                :default-page="insights.meta.current_page"
                :items-per-page="insights.meta.per_page"
                :total="insights.meta.total"
                @update:page="p => reload({ page: p })"
            />
        </div>
    </div>
</template>
