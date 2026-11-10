<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { insightPrompt } from '@/lib/insightPrompt'
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
    counts: Partial<Record<InsightKind, number>>
}>()

const page = usePage()
const toast = useToast()

// Full class strings so Tailwind's scanner sees each color. `about` says
// what the tab holds and what to do with it, in place of a long page intro.
const KINDS: Record<InsightKind, { label: string, tab: string, icon: string, text: string, border: string, about: string, empty: string }> = {
    feature: {
        label: 'Missing feature',
        tab: 'Missing features',
        icon: 'i-lucide-puzzle',
        text: 'text-warning',
        border: 'border-l-warning',
        about: 'Capabilities people ask for on Reddit, or that competitors have and you lack. Copy a prompt to hand one to your coding agent.',
        empty: 'No missing features right now.'
    },
    competitor_mention: {
        label: 'Competitor',
        tab: 'Competitors',
        icon: 'i-lucide-message-square-quote',
        text: 'text-info',
        border: 'border-l-info',
        about: 'What people say about your competitors on Reddit: complaints, comparisons, people looking to switch.',
        empty: 'Nobody mentioned your competitors lately.'
    },
    acquisition: {
        label: 'Acquisition',
        tab: 'Acquisition',
        icon: 'i-lucide-trending-up',
        text: 'text-success',
        border: 'border-l-success',
        about: 'Ways to bring more people to your site that it does not use yet. Copy a prompt to hand one to your coding agent.',
        empty: 'No acquisition ideas right now.'
    },
    article: {
        label: 'Article topic',
        tab: 'Article topics',
        icon: 'i-lucide-lightbulb',
        text: 'text-secondary',
        border: 'border-l-secondary',
        about: 'Questions many Reddit threads keep asking. Every other article idea waits on the SEO page.',
        empty: 'No topic is popular enough yet. The others wait on the SEO page.'
    }
}

const SIGNAL = {
    complaint: { label: 'Complaint', dot: 'bg-error' },
    switching: { label: 'Looking to switch', dot: 'bg-warning' },
    comparison: { label: 'Comparison', dot: 'bg-info' },
    praise: { label: 'Praise', dot: 'bg-success' }
} as const

const LEVEL = { high: 'High', medium: 'Medium', low: 'Low' } as const

const kind = ref<InsightKind | 'all'>(props.filters.kind)
const competitor = ref(props.filters.competitor)
const unread = ref(props.filters.unread)

const total = computed(() => Object.values(props.counts).reduce((sum, count) => sum + (count ?? 0), 0))

const tabs = computed(() => [
    { label: 'Everything', value: 'all', badge: total.value || undefined },
    ...(Object.keys(KINDS) as InsightKind[]).map(value => ({ label: KINDS[value].tab, value, icon: KINDS[value].icon, badge: props.counts[value] || undefined }))
].map(tab => ({ ...tab, badge: tab.badge === undefined ? undefined : { label: String(tab.badge), color: 'neutral' as const, variant: 'subtle' as const } })))

function reload (extra: Record<string, unknown> = {}) {
    router.get(insightRoutes.index.url({ project: page.props.currentProject!.slug }), {
        kind: kind.value === 'all' ? undefined : kind.value,
        competitor: kind.value === 'competitor_mention' && competitor.value !== 'all' ? competitor.value : undefined,
        unread: unread.value ? 1 : undefined,
        ...extra
    }, { preserveState: true, preserveScroll: true, replace: true })
}

watch([kind, competitor, unread], () => reload())

// Held per item: several can be clicked in a row while the first one's
// request is still in flight.
const busy = ref<number | null>(null)

// The idea only turns used once the article is drafted, minutes later, and
// this page does not poll: hide it as soon as the write is queued.
const writing = ref<number[]>([])
const shown = computed(() => props.insights.data.filter(insight => !writing.value.includes(insight.id)))

function headline (insight: Insight): string {
    return insight.kind === 'acquisition' || insight.kind === 'feature' ? insight.title : insight.angle
}

function body (insight: Insight): string | null {
    return insight.kind === 'acquisition' || insight.kind === 'feature' ? insight.angle : null
}

function source (insight: Insight): string | null {
    const subreddit = insight.details.subreddit ?? insight.details.threads?.[0]?.subreddit

    if (insight.source === 'competitors') {
        return 'From competitor sites'
    }

    return subreddit && (insight.details.threads?.length ?? 0) < 2 ? `r/${subreddit}` : null
}

// The one place to read more: the thread, or the page a competitor feature
// came from. Several threads get a list instead.
function link (insight: Insight): { url: string, label: string } | null {
    const threads = insight.details.threads ?? []

    if (threads.length > 1) {
        return null
    }

    if (threads.length === 1) {
        return { url: threads[0].url, label: threads[0].title || 'Read the thread' }
    }

    if (!insight.url) {
        return null
    }

    return {
        url: insight.url,
        label: insight.details.thread_title ?? (insight.kind === 'competitor_mention' ? insight.title : insight.details.subreddit ? 'Read the thread' : 'Open the source')
    }
}

function update (insight: Insight, data: { read?: boolean, status?: 'used' | 'dismissed' }, toastTitle?: string) {
    busy.value = insight.id
    router.put(insightRoutes.update.url({ project: page.props.currentProject!.slug, insight: insight.id }), data, {
        preserveScroll: true,
        onSuccess: () => toastTitle && toast.add({ title: toastTitle, color: 'neutral' }),
        onFinish: () => busy.value = null
    })
}

// Following the link or copying a prompt is reading it.
function markRead (insight: Insight) {
    if (!insight.read_at) {
        router.put(insightRoutes.update.url({ project: page.props.currentProject!.slug, insight: insight.id }), { read: true }, { preserveScroll: true })
    }
}

function writeArticle (insight: Insight) {
    busy.value = insight.id
    router.post(ideaRoutes.write.url({ project: page.props.currentProject!.slug, idea: insight.id }), {}, {
        preserveScroll: true,
        onSuccess: () => {
            writing.value.push(insight.id)
            toast.add({ title: 'Writing that article', description: 'It will appear in the SEO drafts in a few minutes.', color: 'success' })
        },
        onFinish: () => busy.value = null
    })
}

async function copyPrompt (insight: Insight, mode: 'standalone' | 'mcp') {
    try {
        await navigator.clipboard.writeText(insightPrompt(insight, page.props.currentProject!, mode))
        toast.add({ title: 'Prompt copied', description: 'Paste it to your coding agent.', color: 'success' })
        markRead(insight)
    } catch {
        toast.add({ title: 'Could not copy the prompt', color: 'error' })
    }
}

function promptOptions (insight: Insight) {
    return [[
        { label: 'Full prompt', description: 'Every detail, for any agent', icon: 'i-lucide-file-text', onSelect: () => copyPrompt(insight, 'standalone') },
        { label: 'Eveil MCP prompt', description: 'Fetches it, builds it, closes it', icon: 'i-lucide-plug', onSelect: () => copyPrompt(insight, 'mcp') }
    ]]
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
                    What Eveil noticed while reading Reddit and your competitors' sites.
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

        <UTabs
            v-model="kind"
            :items="tabs"
            :content="false"
            variant="link"
            class="overflow-x-auto"
            :ui="{ list: 'w-max min-w-full', trigger: 'shrink-0 grow-0', label: 'overflow-visible' }"
        />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted">
                {{ kind === 'all' ? 'Everything still open, newest first.' : KINDS[kind].about }}
            </p>

            <div class="flex flex-wrap items-center gap-3">
                <USelect
                    v-if="kind === 'competitor_mention' && competitors.length"
                    v-model="competitor"
                    :items="[{ label: 'Every competitor', value: 'all' }, ...competitors.map(name => ({ label: name, value: name }))]"
                    size="sm"
                    class="w-48"
                />
                <USwitch
                    v-model="unread"
                    label="Unread only"
                />
            </div>
        </div>

        <UAlert
            v-if="!competitors.length && (kind === 'all' || kind === 'competitor_mention' || kind === 'feature')"
            color="neutral"
            variant="subtle"
            icon="i-lucide-info"
            title="No competitors named yet"
            description="Name your competitors in the knowledge base and Eveil will watch what people say about them on Reddit, and compare their features with yours every week."
            :actions="[{ label: 'Open the knowledge base', color: 'neutral', variant: 'outline', to: relativeUrl(knowledgeBaseRoutes.edit.url({ project: page.props.currentProject!.slug })) }]"
        />

        <ul
            v-if="shown.length"
            class="divide-y divide-default rounded-lg ring ring-default"
        >
            <li
                v-for="insight in shown"
                :key="insight.id"
                class="group flex flex-col gap-3 border-l-4 px-4 py-3.5 first:rounded-tl-lg last:rounded-bl-lg sm:flex-row sm:items-start"
                :class="[KINDS[insight.kind].border, insight.read_at ? '' : 'bg-elevated/40']"
            >
                <div class="flex min-w-0 flex-1 gap-3">
                    <span
                        class="mt-2 size-1.5 shrink-0 rounded-full"
                        :class="insight.read_at ? 'bg-transparent' : 'bg-primary'"
                        :aria-label="insight.read_at ? undefined : 'Unread'"
                    />

                    <div class="min-w-0 flex-1 space-y-1.5">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
                            <span
                                class="inline-flex items-center gap-1 font-medium"
                                :class="KINDS[insight.kind].text"
                            >
                                <UIcon
                                    :name="KINDS[insight.kind].icon"
                                    class="size-3.5"
                                />
                                {{ insight.details.competitor ?? KINDS[insight.kind].label }}
                            </span>
                            <span
                                v-if="insight.details.signal"
                                class="inline-flex items-center gap-1"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :class="SIGNAL[insight.details.signal].dot"
                                />
                                {{ SIGNAL[insight.details.signal].label }}
                            </span>
                            <span v-if="insight.kind === 'article' || (insight.details.threads?.length ?? 0) > 1">Asked in {{ insight.mentions }} threads</span>
                            <span v-if="insight.details.impact">{{ LEVEL[insight.details.impact] }} impact</span>
                            <span v-if="insight.details.effort">{{ LEVEL[insight.details.effort] }} effort</span>
                            <span v-if="source(insight)">{{ source(insight) }}</span>
                            <span
                                v-if="insight.created_at"
                                class="text-dimmed"
                            >{{ new Date(insight.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) }}</span>
                        </div>

                        <p
                            class="text-sm text-highlighted"
                            :class="insight.read_at ? '' : 'font-medium'"
                        >
                            {{ headline(insight) }}
                        </p>

                        <p
                            v-if="body(insight)"
                            class="text-sm text-muted"
                        >
                            {{ body(insight) }}
                        </p>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                            <a
                                v-if="link(insight) && insight.kind !== 'article'"
                                :href="link(insight)!.url"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex min-w-0 items-center gap-1 text-primary hover:underline"
                                @click="markRead(insight)"
                            >
                                <span class="truncate">{{ link(insight)!.label }}</span>
                                <UIcon
                                    name="i-lucide-arrow-up-right"
                                    class="size-3 shrink-0"
                                />
                            </a>
                            <details
                                v-if="insight.kind === 'article' ? insight.details.threads?.length : (insight.details.threads?.length ?? 0) > 1"
                                class="w-full"
                            >
                                <summary class="cursor-pointer text-muted hover:text-highlighted">
                                    The threads asking
                                </summary>
                                <ul class="mt-1.5 space-y-2">
                                    <li
                                        v-for="thread in insight.details.threads"
                                        :key="thread.url"
                                    >
                                        <a
                                            :href="thread.url"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-primary hover:underline"
                                            @click="markRead(insight)"
                                        >{{ thread.subreddit ? `r/${thread.subreddit}: ` : '' }}{{ thread.title }}</a>
                                        <p
                                            v-if="thread.evidence"
                                            class="mt-0.5 text-muted"
                                        >
                                            {{ thread.evidence }}
                                        </p>
                                    </li>
                                </ul>
                            </details>
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1 pl-4.5 sm:pl-0">
                    <UDropdownMenu
                        v-if="insight.kind === 'feature' || insight.kind === 'acquisition'"
                        :items="promptOptions(insight)"
                    >
                        <UButton
                            icon="i-lucide-bot"
                            trailing-icon="i-lucide-chevron-down"
                            color="neutral"
                            variant="outline"
                            size="xs"
                            label="Prompt"
                        />
                    </UDropdownMenu>
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
                    <UTooltip :text="insight.read_at ? 'Mark unread' : 'Mark read'">
                        <UButton
                            :icon="insight.read_at ? 'i-lucide-mail' : 'i-lucide-mail-open'"
                            color="neutral"
                            variant="ghost"
                            size="xs"
                            :aria-label="insight.read_at ? 'Mark unread' : 'Mark read'"
                            :disabled="busy === insight.id"
                            @click="update(insight, { read: !insight.read_at })"
                        />
                    </UTooltip>
                    <UTooltip text="Dismiss for good">
                        <UButton
                            icon="i-lucide-x"
                            color="neutral"
                            variant="ghost"
                            size="xs"
                            aria-label="Dismiss"
                            :loading="busy === insight.id"
                            @click="update(insight, { status: 'dismissed' }, 'Dismissed')"
                        />
                    </UTooltip>
                </div>
            </li>
        </ul>

        <div
            v-else
            class="flex flex-col items-center gap-2 rounded-lg p-10 text-center ring ring-default"
        >
            <UIcon
                :name="kind === 'all' ? 'i-lucide-inbox' : KINDS[kind].icon"
                class="size-6 text-dimmed"
            />
            <p class="text-sm text-muted">
                {{ unread ? 'Nothing unread here.' : kind === 'all' ? 'Nothing here yet. Insights appear as Eveil scans Reddit and reads your competitors.' : KINDS[kind].empty }}
            </p>
        </div>

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
