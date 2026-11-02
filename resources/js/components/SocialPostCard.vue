<script setup lang="ts">
import { Form, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { openEvieChat } from '@/composables/useChatPanel'
import { PLATFORM_LABEL, PLATFORM_LIMIT, postLength } from '@/lib/social'
import { relativeUrl } from '@/lib/utils'
import socialPostRoutes from '@/routes/social/posts'
import type { SocialAccount, SocialPost } from '@/types'

// One post with every action on it. Shared by `social/Posts.vue` and the
// dashboard's to-review modal, so both review a draft the same way. A
// LinkedIn, Bluesky or Instagram draft is approved and published through the
// network's API; an X draft is copied, posted by hand, and its URL pasted back.
const props = defineProps<{
    post: SocialPost
    /** The project's granted accounts on this post's network. */
    accounts: SocialAccount[]
}>()

// Emitted when the user hands the draft to Evie, so a modal around this card
// can get out of the chat panel's way.
const emit = defineEmits<{ rework: [] }>()

const page = usePage()
const toast = useToast()
const slug = computed(() => page.props.currentProject!.slug)

const STATUS = {
    draft: { color: 'neutral' as const, label: 'Draft' },
    published: { color: 'success' as const, label: 'Published' },
    rejected: { color: 'neutral' as const, label: 'Rejected' }
}

const SOURCE = {
    knowledge_base: 'Knowledge base',
    client_won: 'New client',
    news: 'Industry news',
    article: 'Your article',
    manual: 'Drafted with Evie'
}

const editing = ref(false)
const editBody = ref('')
const approving = ref(false)
const rejecting = ref(false)
const deleting = ref(false)
const promoting = ref(false)
const rejectOpen = ref(false)
const rejectReason = ref('')
const pickingAccount = ref(false)
const selectedAccountId = ref<number | undefined>(undefined)
const publishOpen = ref(false)
const publishedUrl = ref('')
const publishing = ref(false)
const imageOpen = ref(false)
const imageNote = ref('')
const requestingImage = ref(false)
const selectingImage = ref<number | null>(null)
const requestingVideo = ref(false)

const limit = computed(() => PLATFORM_LIMIT[props.post.platform])
const length = computed(() => postLength(props.post.platform, editing.value ? editBody.value : props.post.body))
const label = computed(() => PLATFORM_LABEL[props.post.platform])
const activeAccounts = computed(() => props.accounts.filter(account => account.status === 'active'))

function accountName (account: { handle: string | null, display_name: string }): string {
    return account.handle ? `@${account.handle}` : account.display_name
}

function rework () {
    openEvieChat(`Let's rework the ${label.value} post #${props.post.id}.`)
    emit('rework')
}

function toggleEdit () {
    editing.value = !editing.value
    editBody.value = props.post.body
}

async function copyAndOpenX () {
    try {
        await navigator.clipboard.writeText(props.post.body)
        toast.add({ title: 'Copied', description: 'Paste it on X if the draft does not come through.', color: 'success' })
    } catch {
        toast.add({ title: 'Could not copy - select and copy manually', color: 'error' })
    }

    window.open(`https://x.com/intent/post?text=${encodeURIComponent(props.post.body)}`, '_blank', 'noopener')
}

function approve () {
    // One account: nothing to pick. Several: ask which one.
    if (activeAccounts.value.length > 1) {
        selectedAccountId.value = activeAccounts.value[0].id
        pickingAccount.value = true
        return
    }

    submitApprove(activeAccounts.value[0]?.id)
}

function submitApprove (socialAccountId: number | undefined) {
    if (!socialAccountId) {
        return
    }

    approving.value = true
    router.post(socialPostRoutes.approve.url({ project: slug.value, social_post: props.post.id }), { social_account_id: socialAccountId }, {
        preserveScroll: true,
        onSuccess: () => {
            pickingAccount.value = false
            toast.add({ title: `Publishing to ${label.value}…`, color: 'success' })
        },
        onError: errors => toast.add({ title: errors.social_post ?? 'Could not publish', color: 'error' }),
        onFinish: () => approving.value = false
    })
}

function confirmPublish () {
    publishing.value = true
    router.post(socialPostRoutes.publish.url({ project: slug.value, social_post: props.post.id }), { url: publishedUrl.value }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ title: 'Marked as posted', color: 'success' })
            publishOpen.value = false
        },
        onError: errors => toast.add({ title: errors.url ?? 'Could not save the link', color: 'error' }),
        onFinish: () => publishing.value = false
    })
}

function confirmReject () {
    rejecting.value = true
    router.post(socialPostRoutes.reject.url({ project: slug.value, social_post: props.post.id }), { reason: rejectReason.value }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ title: 'Draft rejected', color: 'neutral' })
            rejectOpen.value = false
        },
        onFinish: () => rejecting.value = false
    })
}

function destroy () {
    deleting.value = true
    router.delete(socialPostRoutes.destroy.url({ project: slug.value, social_post: props.post.id }), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Draft deleted', color: 'neutral' }),
        onFinish: () => deleting.value = false
    })
}

// The new image is drawn on the queue: the page rereads on its own and it
// shows up beside the others. The picked one stays picked.
function requestImage () {
    requestingImage.value = true
    router.post(socialPostRoutes.images.store.url({ project: slug.value, social_post: props.post.id }), { note: imageNote.value }, {
        preserveScroll: true,
        onSuccess: () => {
            imageOpen.value = false
            imageNote.value = ''
            toast.add({ title: 'Drawing a new image', description: 'It appears here in a moment, next to the others.', color: 'success' })
        },
        onFinish: () => requestingImage.value = false
    })
}

function selectImage (imageId: number) {
    selectingImage.value = imageId
    router.put(socialPostRoutes.images.update.url({ project: slug.value, social_post: props.post.id, image: imageId }), {}, {
        preserveScroll: true,
        onFinish: () => selectingImage.value = null
    })
}

const selectingVideo = ref(false)

function selectVideo () {
    selectingVideo.value = true
    router.put(socialPostRoutes.video.update.url({ project: slug.value, social_post: props.post.id }), {}, {
        preserveScroll: true,
        onFinish: () => selectingVideo.value = false
    })
}

// The pictures drawn for it, or on a post already out, only the one that went.
const shownImages = computed(() => {
    const images = props.post.images?.length ? props.post.images : (props.post.image_url ? [{ id: 0, url: props.post.image_url, selected: true }] : [])

    return props.post.status === 'draft' ? images : images.filter(image => image.selected && !props.post.publish_video)
})

// A choice only where there is one to make: a draft with more than one
// medium, on a network that publishes for us (X is attached by hand).
const choosing = computed(() => props.post.status === 'draft' && props.post.platform !== 'x' && !!props.post.images?.length && shownImages.value.length + (props.post.video_url ? 1 : 0) > 1)
const picking = computed(() => selectingImage.value !== null || selectingVideo.value)

function isPicked (kind: 'image' | 'video', imageId?: number): boolean {
    return kind === 'video'
        ? !!props.post.publish_video
        : !props.post.publish_video && !!shownImages.value.find(image => image.id === imageId)?.selected
}

function mediaRing (picked: boolean): string {
    return choosing.value && picked ? 'ring-primary' : 'ring-default'
}

// The first version of the video. Made on the queue: the page rereads on
// its own and the video shows up beside the image.
function requestVideo () {
    requestingVideo.value = true
    router.post(socialPostRoutes.video.store.url({ project: slug.value, social_post: props.post.id }), {}, {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Making the video', description: 'It appears here in a minute or two.', color: 'success' }),
        onError: errors => toast.add({ title: errors.feedback ?? 'Could not start the video', color: 'error' }),
        onFinish: () => requestingVideo.value = false
    })
}

function promote () {
    promoting.value = true
    router.post(socialPostRoutes.promote.url({ project: slug.value, social_post: props.post.id }), {}, {
        preserveScroll: true,
        onSuccess: () => toast.add({
            title: 'Marked as successful',
            description: 'Your writer will use this as an example for future posts on this project.',
            color: 'success'
        }),
        onFinish: () => promoting.value = false
    })
}
</script>

<template>
    <div class="space-y-3 rounded-lg p-4 ring ring-default">
        <div class="flex flex-wrap items-center gap-3">
            <UBadge
                color="neutral"
                variant="subtle"
                :label="SOURCE[post.source_type]"
            />
            <UBadge
                v-if="post.variant"
                color="neutral"
                variant="outline"
                :label="post.variant === 'named' ? 'Names the client' : 'Anonymized'"
            />
            <UBadge
                :color="STATUS[post.status].color"
                variant="subtle"
                :label="STATUS[post.status].label"
            />
            <UBadge
                v-if="post.social_account && accounts.length > 1"
                color="neutral"
                variant="outline"
                :label="accountName(post.social_account)"
            />
            <span
                v-if="post.status === 'published' && post.platform !== 'x'"
                class="text-xs text-muted"
            >{{ post.likes_count }} {{ post.likes_count === 1 ? 'like' : 'likes' }}</span>

            <div class="ml-auto flex flex-wrap items-center gap-2">
                <UButton
                    v-if="post.status === 'draft'"
                    icon="i-lucide-sparkles"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    label="Rework with Evie"
                    @click="rework"
                />
                <UButton
                    v-if="post.status === 'draft'"
                    icon="i-lucide-pencil"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    label="Edit"
                    @click="toggleEdit"
                />
                <template v-if="post.status === 'draft' && post.platform === 'x'">
                    <UButton
                        icon="i-lucide-external-link"
                        color="neutral"
                        variant="subtle"
                        size="xs"
                        label="Copy & open X"
                        @click="copyAndOpenX"
                    />
                    <UButton
                        icon="i-lucide-check"
                        color="success"
                        variant="subtle"
                        size="xs"
                        label="Mark as posted"
                        @click="publishedUrl = ''; publishOpen = true"
                    />
                </template>
                <UButton
                    v-if="post.status === 'draft' && post.platform !== 'x'"
                    icon="i-lucide-check"
                    color="success"
                    variant="subtle"
                    size="xs"
                    label="Approve & publish"
                    :loading="approving"
                    :disabled="!activeAccounts.length || post.drawing_image || post.making_video"
                    @click="approve"
                />
                <UButton
                    v-if="post.status === 'draft'"
                    icon="i-lucide-x"
                    color="error"
                    variant="ghost"
                    size="xs"
                    label="Reject"
                    @click="rejectReason = ''; rejectOpen = true"
                />
                <UButton
                    v-if="post.status === 'published' && !post.promoted_at"
                    icon="i-lucide-thumbs-up"
                    color="success"
                    variant="ghost"
                    size="xs"
                    label="Mark as successful"
                    :loading="promoting"
                    @click="promote"
                />
                <UBadge
                    v-if="post.promoted_at"
                    color="success"
                    variant="subtle"
                    label="Marked as successful"
                />
                <UButton
                    icon="i-lucide-trash"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    :loading="deleting"
                    @click="destroy"
                />
            </div>
        </div>

        <p class="text-sm text-dimmed">
            {{ post.evidence }}
        </p>

        <div
            v-if="post.image_url || post.platform === 'instagram' || post.video_url || post.making_video || post.status === 'draft'"
            class="space-y-2"
        >
            <!-- Every picture drawn and the video, side by side: on a draft, the
                 one ringed is what goes out, and a click picks another. -->
            <div class="flex flex-wrap items-start gap-3">
                <figure
                    v-for="image in shownImages"
                    :key="image.id"
                    class="relative size-56 shrink-0 overflow-hidden rounded-lg ring-2 transition"
                    :class="mediaRing(isPicked('image', image.id))"
                >
                    <button
                        v-if="choosing"
                        type="button"
                        class="block size-full"
                        :aria-label="isPicked('image', image.id) ? 'This image is what gets published' : 'Publish this image'"
                        :aria-pressed="isPicked('image', image.id)"
                        :disabled="picking"
                        @click="isPicked('image', image.id) || selectImage(image.id)"
                    >
                        <img
                            :src="image.url"
                            alt=""
                            class="size-full object-cover"
                        >
                    </button>
                    <img
                        v-else
                        :src="image.url"
                        alt="The image published with this post"
                        class="size-full object-cover"
                    >
                    <UButton
                        v-if="choosing"
                        class="absolute top-2 left-2"
                        size="xs"
                        :color="isPicked('image', image.id) ? 'primary' : 'neutral'"
                        :variant="isPicked('image', image.id) ? 'solid' : 'outline'"
                        :icon="isPicked('image', image.id) ? 'i-lucide-check' : undefined"
                        :label="isPicked('image', image.id) ? 'Publishing' : 'Use this'"
                        :loading="selectingImage === image.id"
                        :disabled="picking"
                        :ui="{ base: isPicked('image', image.id) ? '' : 'bg-default/90' }"
                        @click="isPicked('image', image.id) || selectImage(image.id)"
                    />
                </figure>

                <!-- Its first image, still being drawn. -->
                <div
                    v-if="post.drawing_image"
                    class="flex size-56 shrink-0 animate-pulse items-center justify-center gap-2 rounded-lg bg-elevated text-sm text-muted"
                >
                    <UIcon
                        name="i-lucide-loader-circle"
                        class="size-4 animate-spin"
                    />
                    Drawing an image…
                </div>

                <figure
                    v-if="post.video_url && (choosing || post.publish_video || !shownImages.length)"
                    class="relative h-56 shrink-0 overflow-hidden rounded-lg bg-black ring-2 transition"
                    :class="mediaRing(isPicked('video'))"
                    :style="{ aspectRatio: `${post.video_size[0]} / ${post.video_size[1]}` }"
                >
                    <video
                        :key="post.video_url"
                        :src="post.video_url"
                        controls
                        playsinline
                        preload="metadata"
                        class="size-full object-cover"
                    />
                    <UButton
                        v-if="choosing"
                        class="absolute top-2 left-2"
                        size="xs"
                        :color="isPicked('video') ? 'primary' : 'neutral'"
                        :variant="isPicked('video') ? 'solid' : 'outline'"
                        :icon="isPicked('video') ? 'i-lucide-check' : undefined"
                        :label="isPicked('video') ? (post.platform === 'instagram' ? 'Publishing as a Reel' : 'Publishing') : 'Use this'"
                        :loading="selectingVideo"
                        :disabled="picking"
                        :ui="{ base: isPicked('video') ? '' : 'bg-default/90' }"
                        @click="isPicked('video') || selectVideo()"
                    />
                </figure>
                <div
                    v-else-if="post.making_video"
                    :style="{ aspectRatio: `${post.video_size[0]} / ${post.video_size[1]}` }"
                    class="flex h-56 shrink-0 animate-pulse flex-col items-center justify-center gap-2 rounded-lg bg-elevated p-3 text-center text-sm text-muted"
                >
                    <UIcon
                        name="i-lucide-loader-circle"
                        class="size-4 animate-spin"
                    />
                    Making the video…
                </div>
            </div>

            <p
                v-if="choosing"
                class="text-xs text-dimmed"
            >
                Click an image or the video to choose what goes out with the post.
            </p>
            <p
                v-if="post.video_url && post.platform === 'x' && post.status === 'draft'"
                class="text-xs text-dimmed"
            >
                Download the video and attach it when you post.
            </p>

            <div class="flex flex-wrap gap-2">
                <UButton
                    v-if="post.status === 'draft' && post.platform === 'instagram'"
                    icon="i-lucide-image-plus"
                    color="neutral"
                    variant="subtle"
                    size="xs"
                    :label="post.drawing_image ? 'Drawing a new image…' : 'New image'"
                    :loading="post.drawing_image"
                    :disabled="post.drawing_image"
                    @click="imageOpen = true"
                />
                <UButton
                    v-if="post.video_url && post.platform === 'x'"
                    :href="post.video_url"
                    download
                    icon="i-lucide-download"
                    color="neutral"
                    variant="subtle"
                    size="xs"
                    label="Download the video"
                />
                <UButton
                    v-if="post.status === 'draft' && post.video_preview_url"
                    :to="relativeUrl(socialPostRoutes.video.show.url({ project: slug, social_post: post.id }))"
                    icon="i-lucide-clapperboard"
                    color="neutral"
                    variant="subtle"
                    size="xs"
                    label="Edit the video"
                />
                <UButton
                    v-else-if="post.status === 'draft'"
                    icon="i-lucide-clapperboard"
                    color="neutral"
                    variant="subtle"
                    size="xs"
                    :label="post.making_video ? 'Making the video…' : 'Make a video'"
                    :loading="post.making_video || requestingVideo"
                    :disabled="post.making_video"
                    @click="requestVideo"
                />
            </div>
        </div>

        <Form
            v-if="editing"
            v-slot="{ errors, processing }"
            v-bind="socialPostRoutes.update.form({ project: slug, social_post: post.id })"
            class="space-y-2"
            :options="{ preserveScroll: true }"
            @success="editing = false"
        >
            <UFormField
                name="body"
                :error="errors.body"
            >
                <UTextarea
                    v-model="editBody"
                    name="body"
                    :rows="4"
                    class="w-full"
                />
            </UFormField>
            <UButton
                type="submit"
                size="xs"
                label="Save"
                :loading="processing"
            />
        </Form>
        <p
            v-else
            class="whitespace-pre-line text-sm"
        >
            {{ post.body }}
        </p>

        <p
            v-if="post.status === 'draft'"
            class="text-xs"
            :class="length > limit ? 'text-error' : 'text-dimmed'"
        >
            {{ length }} / {{ limit }}
        </p>

        <a
            v-if="post.url"
            :href="post.url"
            target="_blank"
            rel="noopener"
            class="text-sm text-primary"
        >View on {{ label }}</a>

        <p
            v-if="post.last_error"
            class="text-sm text-error"
        >
            {{ post.last_error }}
        </p>

        <p
            v-if="post.status === 'rejected' && post.rejection_reason"
            class="text-sm text-dimmed"
        >
            Rejected: {{ post.rejection_reason }}
        </p>

        <UModal
            v-model:open="imageOpen"
            title="Draw a new image"
            description="The current images stay: you pick which one is published."
        >
            <template #body>
                <div class="space-y-4">
                    <UFormField
                        label="What should change? (optional)"
                        description="Leave it empty for another take on the same idea."
                    >
                        <UTextarea
                            v-model="imageNote"
                            :rows="3"
                            :maxlength="500"
                            placeholder="E.g. brighter, a close-up, an outdoor setting."
                            class="w-full"
                        />
                    </UFormField>

                    <UButton
                        icon="i-lucide-image-plus"
                        label="Draw it"
                        :loading="requestingImage"
                        @click="requestImage"
                    />
                </div>
            </template>
        </UModal>

        <UModal
            v-model:open="publishOpen"
            title="Mark as posted on X"
        >
            <template #body>
                <UFormField
                    label="Link to your post"
                    description="Open the post on X and copy its address."
                >
                    <UInput
                        v-model="publishedUrl"
                        class="w-full"
                        placeholder="https://x.com/you/status/..."
                    />
                </UFormField>
            </template>

            <template #footer>
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Cancel"
                    @click="publishOpen = false"
                />
                <UButton
                    color="success"
                    label="Mark as posted"
                    :disabled="!publishedUrl"
                    :loading="publishing"
                    @click="confirmPublish"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="rejectOpen"
            title="Reject this draft"
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
                    />
                </UFormField>
            </template>

            <template #footer>
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Cancel"
                    @click="rejectOpen = false"
                />
                <UButton
                    color="error"
                    label="Reject"
                    :loading="rejecting"
                    @click="confirmReject"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="pickingAccount"
            title="Publish to which account?"
        >
            <template #body>
                <UFormField :label="`${label} account`">
                    <USelect
                        v-model="selectedAccountId"
                        :items="activeAccounts.map(account => ({ label: accountName(account), value: account.id }))"
                        class="w-full"
                    />
                </UFormField>
            </template>

            <template #footer>
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Cancel"
                    @click="pickingAccount = false"
                />
                <UButton
                    color="success"
                    label="Approve & publish"
                    :loading="approving"
                    @click="submitApprove(selectedAccountId)"
                />
            </template>
        </UModal>
    </div>
</template>
