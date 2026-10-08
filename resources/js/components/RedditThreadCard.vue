<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { openEvieChat } from '@/composables/useChatPanel'
import type { RedditThread } from '@/lib/reddit'
import redditReplyRoutes from '@/routes/reddit/replies'
import type { RedditReply } from '@/types'

// One thread and its drafted angles, with every action on them. Shared by
// `reddit/Replies.vue` and the dashboard's to-review modal, so both review a
// draft exactly the same way.
const props = defineProps<{
    thread: RedditThread
}>()

// Emitted when the user hands a draft to Evie, so a modal around this card
// can close and let the chat panel show.
const emit = defineEmits<{
    rework: []
}>()

const page = usePage()
const toast = useToast()

const ANGLE_LABEL = {
    value_comment: 'Value comment',
    soft_mention: 'Soft mention',
    direct_mention: 'Direct mention',
    dm_invite: 'DM invite',
    user_written: 'Written by you'
}

const STATUS = {
    draft: { color: 'neutral' as const, label: 'Draft' },
    published: { color: 'success' as const, label: 'Posted' },
    rejected: { color: 'neutral' as const, label: 'Rejected' }
}

const selectedReplyId = ref<string | number>()

// Falls back to the first angle whenever the selected one leaves this thread
// (rejected, deleted, or a poll reshuffling the list).
const activeReply = computed(() => props.thread.replies.find(reply => String(reply.id) === String(selectedReplyId.value)) ?? props.thread.replies[0])

const activeReplyId = computed({
    get: () => activeReply.value ? String(activeReply.value.id) : undefined,
    set: value => selectedReplyId.value = value
})

const angleTabs = computed(() => props.thread.replies.map(reply => ({
    label: ANGLE_LABEL[reply.angle],
    value: String(reply.id)
})))

const isDraft = computed(() => props.thread.replies.every(reply => reply.status === 'draft'))

const approving = ref<number | null>(null)
const rejecting = ref<number | null>(null)
const deleting = ref<number | null>(null)
const deletingThread = ref(false)
const promoting = ref<number | null>(null)
const rejectingReply = ref<RedditReply | null>(null)
const rejectReason = ref('')
const markingPosted = ref<RedditReply | null>(null)
const commentPermalink = ref('')
const writingManual = ref(false)
const manualBody = ref('')
const manualPermalink = ref('')
const submittingManual = ref(false)

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

function openWriteManual () {
    writingManual.value = true
    manualBody.value = ''
    manualPermalink.value = ''
}

function confirmWriteManual () {
    submittingManual.value = true
    router.post(redditReplyRoutes.manual.url({ project: page.props.currentProject!.slug }), {
        thread_permalink: props.thread.permalink,
        body: manualBody.value,
        comment_permalink: manualPermalink.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ title: 'Marked as posted', color: 'success' })
            writingManual.value = false
        },
        onFinish: () => submittingManual.value = false
    })
}

function rework (reply: RedditReply) {
    openEvieChat(`Let's rework the ${ANGLE_LABEL[reply.angle].toLowerCase()} Reddit reply #${reply.id} on "${props.thread.threadTitle ?? props.thread.permalink}".`)
    emit('rework')
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

function destroyThread () {
    deletingThread.value = true
    router.delete(redditReplyRoutes.destroyThread.url({ project: page.props.currentProject!.slug }, { query: { thread_permalink: props.thread.permalink } }), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Drafts deleted', color: 'neutral' }),
        onFinish: () => deletingThread.value = false
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
    <div class="space-y-3 rounded-lg p-4 ring ring-default">
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

            <div
                v-if="isDraft"
                class="ml-auto flex items-center gap-2"
            >
                <UButton
                    icon="i-lucide-pencil"
                    color="neutral"
                    variant="subtle"
                    size="xs"
                    label="I wrote my own"
                    @click="openWriteManual"
                />
                <UButton
                    icon="i-lucide-trash"
                    color="error"
                    variant="ghost"
                    size="xs"
                    label="Delete drafts"
                    :loading="deletingThread"
                    @click="destroyThread"
                />
            </div>
        </div>

        <p class="text-sm text-dimmed">
            {{ thread.evidence }}
        </p>

        <!-- One angle at a time: up to four drafts side by side squeezed
             each body into a narrow column, and a long one ran off the card. -->
        <UTabs
            v-model="activeReplyId"
            :items="angleTabs"
            :content="false"
            variant="link"
        />

        <div
            v-if="activeReply"
            class="space-y-3"
        >
            <div class="flex flex-wrap items-center gap-2">
                <UBadge
                    :color="STATUS[activeReply.status].color"
                    variant="subtle"
                    :label="STATUS[activeReply.status].label"
                />
                <UBadge
                    v-if="activeReply.promoted_at"
                    color="success"
                    variant="subtle"
                    label="Marked as proven"
                />
                <span
                    v-if="activeReply.status === 'published' && activeReply.stats_checked_at"
                    class="text-sm text-dimmed"
                >Score: {{ activeReply.score }}</span>
            </div>

            <p class="whitespace-pre-line rounded-lg bg-elevated/50 p-4 text-sm">
                {{ activeReply.body }}
            </p>

            <p
                v-if="activeReply.status === 'rejected' && activeReply.rejection_reason"
                class="text-sm text-dimmed"
            >
                Rejected: {{ activeReply.rejection_reason }}
            </p>

            <a
                v-if="activeReply.comment_permalink"
                :href="activeReply.comment_permalink"
                target="_blank"
                rel="noopener"
                class="text-sm text-primary"
            >View the posted comment ↗</a>

            <div class="flex flex-wrap items-center gap-2">
                <UButton
                    icon="i-lucide-copy"
                    color="neutral"
                    variant="subtle"
                    size="sm"
                    label="Copy"
                    @click="copy(activeReply.body)"
                />
                <template v-if="activeReply.status === 'draft'">
                    <UButton
                        icon="i-lucide-sparkles"
                        color="neutral"
                        variant="subtle"
                        size="sm"
                        label="Rework with Evie"
                        @click="rework(activeReply)"
                    />
                    <UButton
                        icon="i-lucide-check"
                        color="success"
                        variant="subtle"
                        size="sm"
                        label="Mark as posted"
                        :loading="approving === activeReply.id"
                        @click="openMarkPosted(activeReply)"
                    />
                    <UButton
                        icon="i-lucide-x"
                        color="error"
                        variant="ghost"
                        size="sm"
                        label="Reject"
                        @click="openReject(activeReply)"
                    />
                </template>
                <UButton
                    v-if="activeReply.status === 'published' && !activeReply.promoted_at"
                    icon="i-lucide-thumbs-up"
                    color="success"
                    variant="ghost"
                    size="sm"
                    label="Mark as proven"
                    :loading="promoting === activeReply.id"
                    @click="promote(activeReply)"
                />
                <UButton
                    icon="i-lucide-trash"
                    color="neutral"
                    variant="ghost"
                    size="sm"
                    class="ml-auto"
                    aria-label="Delete this draft"
                    :loading="deleting === activeReply.id"
                    @click="destroy(activeReply)"
                />
            </div>
        </div>

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
            v-model:open="writingManual"
            title="I wrote my own reply"
        >
            <template #body>
                <div class="space-y-4">
                    <UFormField
                        label="What you posted"
                        description="The actual text you posted, not one of the drafts - this is what Eveil learns from once it earns enough upvotes."
                    >
                        <UTextarea
                            v-model="manualBody"
                            :rows="5"
                            class="w-full"
                            placeholder="Paste the reply you actually posted..."
                        />
                    </UFormField>

                    <UFormField
                        label="Link to the comment"
                        description="Required - it's what lets Eveil check its score later."
                    >
                        <UInput
                            v-model="manualPermalink"
                            class="w-full"
                            placeholder="https://www.reddit.com/r/.../comment/..."
                        />
                    </UFormField>
                </div>
            </template>

            <template #footer>
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Cancel"
                    @click="writingManual = false"
                />
                <UButton
                    color="success"
                    label="Mark as posted"
                    :disabled="!manualBody || !manualPermalink"
                    :loading="submittingManual"
                    @click="confirmWriteManual"
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
