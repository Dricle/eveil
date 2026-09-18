<script setup lang="ts">
import { Form, Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import redditReplyRoutes from '@/routes/reddit/replies'
import type { RedditReply } from '@/types'

defineOptions({ layout: AppLayout })

const props = defineProps<{
    replies: RedditReply[]
    scanFrequency: 'off' | 'daily' | 'weekly' | 'biweekly' | 'monthly'
}>()

const page = usePage()
const toast = useToast()

// New drafts appear on their own schedule (the cadence tick, or a manual
// scan), so this screen rereads rather than sitting still - same reasoning
// as `linkedin/Posts.vue`.
usePoll(15000, { only: ['replies'] })

const frequency = ref(props.scanFrequency)
watch(() => props.scanFrequency, value => frequency.value = value, { immediate: true })

const FREQUENCIES = [
    { label: 'Off', value: 'off' },
    { label: 'Daily', value: 'daily' },
    { label: 'Weekly', value: 'weekly' },
    { label: 'Every two weeks', value: 'biweekly' },
    { label: 'Monthly', value: 'monthly' }
]

const ANGLE_LABEL = {
    value_comment: 'Value comment',
    soft_mention: 'Soft mention',
    dm_invite: 'DM invite'
}

const STATUS = {
    draft: { color: 'neutral' as const, label: 'Draft' },
    published: { color: 'success' as const, label: 'Posted' },
    rejected: { color: 'neutral' as const, label: 'Rejected' }
}

type Thread = {
    permalink: string
    subreddit: string | null
    threadTitle: string | null
    source: 'subreddit_scan' | 'seo_thread'
    searchQuery: string | null
    evidence: string
    replies: RedditReply[]
}

// Grouped client-side: up to 3 angle drafts share one thread_permalink.
const threads = computed<Thread[]>(() => {
    const byPermalink = new Map<string, Thread>()

    for (const reply of props.replies) {
        let thread = byPermalink.get(reply.thread_permalink)

        if (!thread) {
            thread = {
                permalink: reply.thread_permalink,
                subreddit: reply.subreddit,
                threadTitle: reply.thread_title,
                source: reply.source,
                searchQuery: reply.search_query,
                evidence: reply.evidence,
                replies: []
            }
            byPermalink.set(reply.thread_permalink, thread)
        }

        thread.replies.push(reply)
    }

    return Array.from(byPermalink.values())
})

const scanning = ref(false)
const approving = ref<number | null>(null)
const rejecting = ref<number | null>(null)
const deleting = ref<number | null>(null)
const promoting = ref<number | null>(null)
const rejectingReply = ref<RedditReply | null>(null)
const rejectReason = ref('')
const markingPosted = ref<RedditReply | null>(null)
const commentPermalink = ref('')

function scan () {
    scanning.value = true
    router.post(redditReplyRoutes.scan.url({ project: page.props.currentProject!.slug }), {}, {
        preserveScroll: true,
        onFinish: () => scanning.value = false
    })
}

async function copy (body: string) {
    try {
        await navigator.clipboard.writeText(body)
        toast.add({ title: 'Copied', color: 'success' })
    } catch {
        toast.add({ title: 'Could not copy - select and copy manually', color: 'error' })
    }
}

function openMarkPosted (reply: RedditReply) {
    markingPosted.value = reply
    commentPermalink.value = ''
}

function confirmMarkPosted () {
    const reply = markingPosted.value
    if (!reply) {
        return
    }

    approving.value = reply.id
    router.post(redditReplyRoutes.approve.url({ project: page.props.currentProject!.slug, reddit_reply: reply.id }), {
        comment_permalink: commentPermalink.value || null
    }, {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Marked as posted', color: 'success' }),
        onFinish: () => {
            approving.value = null
            markingPosted.value = null
        }
    })
}

function openReject (reply: RedditReply) {
    rejectingReply.value = reply
    rejectReason.value = ''
}

function confirmReject () {
    const reply = rejectingReply.value
    if (!reply) {
        return
    }

    rejecting.value = reply.id
    router.post(redditReplyRoutes.reject.url({ project: page.props.currentProject!.slug, reddit_reply: reply.id }), { reason: rejectReason.value }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ title: 'Draft rejected', color: 'neutral' })
            rejectingReply.value = null
        },
        onFinish: () => rejecting.value = null
    })
}

function destroy (reply: RedditReply) {
    deleting.value = reply.id
    router.delete(redditReplyRoutes.destroy.url({ project: page.props.currentProject!.slug, reddit_reply: reply.id }), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Draft deleted', color: 'neutral' }),
        onFinish: () => deleting.value = null
    })
}

function promote (reply: RedditReply) {
    promoting.value = reply.id
    router.post(redditReplyRoutes.promote.url({ project: page.props.currentProject!.slug, reddit_reply: reply.id }), {}, {
        preserveScroll: true,
        onSuccess: () => toast.add({
            title: 'Marked as proven',
            description: 'Your writer will use this as an example for future replies on this project.',
            color: 'success'
        }),
        onFinish: () => promoting.value = null
    })
}
</script>

<template>
    <Head title="Reddit replies" />

    <div class="space-y-4 p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-medium">
                    Reddit replies
                </h2>
                <p class="text-sm text-muted">
                    Nothing posts on its own - copy a draft, post it on reddit.com
                    yourself, then come back and mark it posted. Subreddit rules are
                    your own call.
                </p>
            </div>

            <div class="flex items-end gap-3">
                <Form
                    v-slot="{ processing }"
                    v-bind="redditReplyRoutes.cadence.form({ project: page.props.currentProject!.slug })"
                    class="flex items-end gap-3"
                >
                    <UFormField label="Scan">
                        <USelect
                            v-model="frequency"
                            name="reddit_scan_frequency"
                            :items="FREQUENCIES"
                            class="w-44"
                        />
                    </UFormField>

                    <UButton
                        type="submit"
                        label="Save"
                        :loading="processing"
                    />
                </Form>

                <UButton
                    icon="i-lucide-search"
                    color="neutral"
                    variant="subtle"
                    label="Scan now"
                    :loading="scanning"
                    @click="scan"
                />
            </div>
        </div>

        <UAlert
            v-if="page.props.status"
            color="success"
            variant="subtle"
            icon="i-lucide-check"
            :description="String(page.props.status)"
        />

        <div
            v-for="thread in threads"
            :key="thread.permalink"
            class="space-y-3 rounded-lg p-4 ring ring-default"
        >
            <div class="flex flex-wrap items-center gap-2">
                <UBadge
                    v-if="thread.source === 'subreddit_scan'"
                    color="neutral"
                    variant="subtle"
                    :label="thread.subreddit ? `r/${thread.subreddit}` : 'Found in a tracked subreddit'"
                />
                <UBadge
                    v-else
                    color="primary"
                    variant="subtle"
                    :label="`Ranks on Google for '${thread.searchQuery}'`"
                />
                <a
                    :href="thread.permalink"
                    target="_blank"
                    rel="noopener"
                    class="text-sm text-primary"
                >{{ thread.threadTitle ?? 'Open thread on Reddit' }} ↗</a>
            </div>

            <p class="text-sm text-dimmed">
                {{ thread.evidence }}
            </p>

            <div class="grid gap-3 md:grid-cols-3">
                <div
                    v-for="reply in thread.replies"
                    :key="reply.id"
                    class="space-y-2 rounded-lg p-3 ring ring-default"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <UBadge
                            color="neutral"
                            variant="outline"
                            :label="ANGLE_LABEL[reply.angle]"
                        />
                        <UBadge
                            :color="STATUS[reply.status].color"
                            variant="subtle"
                            :label="STATUS[reply.status].label"
                        />
                    </div>

                    <p class="whitespace-pre-line text-sm">
                        {{ reply.body }}
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                        <UButton
                            icon="i-lucide-copy"
                            color="neutral"
                            variant="ghost"
                            size="xs"
                            label="Copy"
                            @click="copy(reply.body)"
                        />
                        <UButton
                            v-if="reply.status === 'draft'"
                            icon="i-lucide-check"
                            color="success"
                            variant="subtle"
                            size="xs"
                            label="Mark as posted"
                            :loading="approving === reply.id"
                            @click="openMarkPosted(reply)"
                        />
                        <UButton
                            v-if="reply.status === 'draft'"
                            icon="i-lucide-x"
                            color="error"
                            variant="ghost"
                            size="xs"
                            label="Reject"
                            @click="openReject(reply)"
                        />
                        <UButton
                            v-if="reply.status === 'published' && !reply.promoted_at"
                            icon="i-lucide-thumbs-up"
                            color="success"
                            variant="ghost"
                            size="xs"
                            label="Mark as proven"
                            :loading="promoting === reply.id"
                            @click="promote(reply)"
                        />
                        <UBadge
                            v-if="reply.promoted_at"
                            color="success"
                            variant="subtle"
                            label="Marked as proven"
                        />
                        <UButton
                            icon="i-lucide-trash"
                            color="neutral"
                            variant="ghost"
                            size="xs"
                            :loading="deleting === reply.id"
                            @click="destroy(reply)"
                        />
                    </div>

                    <p
                        v-if="reply.status === 'published' && reply.stats_checked_at"
                        class="text-sm text-dimmed"
                    >
                        Score: {{ reply.score }}
                    </p>

                    <a
                        v-if="reply.comment_permalink"
                        :href="reply.comment_permalink"
                        target="_blank"
                        rel="noopener"
                        class="text-sm text-primary"
                    >View the posted comment</a>

                    <p
                        v-if="reply.status === 'rejected' && reply.rejection_reason"
                        class="text-sm text-dimmed"
                    >
                        Rejected: {{ reply.rejection_reason }}
                    </p>
                </div>
            </div>
        </div>

        <p
            v-if="!threads.length"
            class="rounded-lg p-6 text-sm text-muted ring ring-default"
        >
            No drafts yet. Turn on scanning above, or scan now.
        </p>

        <UModal
            :open="markingPosted !== null"
            title="Mark as posted"
            @update:open="(value: boolean) => { if (!value) markingPosted = null }"
        >
            <template #body>
                <UFormField
                    label="Link to the comment you posted (optional)"
                    description="Pasting it back is what lets Eveil check its score later and, if it does well, add it to the shared bank of proven replies."
                >
                    <UInput
                        v-model="commentPermalink"
                        class="w-full"
                        placeholder="https://www.reddit.com/r/.../comment/..."
                    />
                </UFormField>
            </template>

            <template #footer>
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Cancel"
                    @click="markingPosted = null"
                />
                <UButton
                    color="success"
                    label="Mark as posted"
                    :loading="approving === markingPosted?.id"
                    @click="confirmMarkPosted"
                />
            </template>
        </UModal>

        <UModal
            :open="rejectingReply !== null"
            title="Reject this draft"
            @update:open="(value: boolean) => { if (!value) rejectingReply = null }"
        >
            <template #body>
                <UFormField
                    label="Why? (optional)"
                    description="Fed back to the writer so it doesn't repeat this."
                >
                    <UTextarea
                        v-model="rejectReason"
                        :rows="4"
                        class="w-full"
                        placeholder="Too pushy, wrong tone, ..."
                    />
                </UFormField>
            </template>

            <template #footer>
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Cancel"
                    @click="rejectingReply = null"
                />
                <UButton
                    color="error"
                    label="Reject"
                    :loading="rejecting === rejectingReply?.id"
                    @click="confirmReject"
                />
            </template>
        </UModal>
    </div>
</template>
