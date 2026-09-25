<script setup lang="ts">
import { Form, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { PLATFORM_LABEL, PLATFORM_LIMIT, postLength } from '@/lib/social'
import socialPostRoutes from '@/routes/social/posts'
import type { SocialAccount, SocialPost } from '@/types'

// One X or Bluesky post with every action on it. Shared by `social/Posts.vue`
// and the dashboard's to-review modal, so both review a draft the same way.
// A Bluesky draft is approved and published through its API; an X draft is
// copied, posted by hand, and its URL pasted back.
const props = defineProps<{
    post: SocialPost
    blueskyAccounts: SocialAccount[]
}>()

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

const limit = computed(() => PLATFORM_LIMIT[props.post.platform])
const length = computed(() => postLength(props.post.platform, editing.value ? editBody.value : props.post.body))
const activeAccounts = computed(() => props.blueskyAccounts.filter(account => account.status === 'active'))

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
            toast.add({ title: 'Publishing to Bluesky…', color: 'success' })
        },
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
                variant="solid"
                :label="PLATFORM_LABEL[post.platform]"
            />
            <UBadge
                color="neutral"
                variant="subtle"
                :label="SOURCE[post.source_type]"
            />
            <UBadge
                :color="STATUS[post.status].color"
                variant="subtle"
                :label="STATUS[post.status].label"
            />
            <UBadge
                v-if="post.social_account && blueskyAccounts.length > 1"
                color="neutral"
                variant="outline"
                :label="`@${post.social_account.handle}`"
            />
            <span
                v-if="post.status === 'published' && post.platform === 'bluesky'"
                class="text-xs text-muted"
            >{{ post.likes_count }} {{ post.likes_count === 1 ? 'like' : 'likes' }}</span>

            <div class="ml-auto flex flex-wrap items-center gap-2">
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
                    v-if="post.status === 'draft' && post.platform === 'bluesky'"
                    icon="i-lucide-check"
                    color="success"
                    variant="subtle"
                    size="xs"
                    label="Approve & publish"
                    :loading="approving"
                    :disabled="!activeAccounts.length"
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

        <Form
            v-if="editing"
            v-slot="{ processing }"
            v-bind="socialPostRoutes.update.form({ project: slug, social_post: post.id })"
            class="space-y-2"
            :options="{ preserveScroll: true }"
            @success="editing = false"
        >
            <UTextarea
                v-model="editBody"
                name="body"
                :rows="4"
                class="w-full"
            />
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
        >View on {{ PLATFORM_LABEL[post.platform] }}</a>

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
                <UFormField label="Bluesky account">
                    <USelect
                        v-model="selectedAccountId"
                        :items="activeAccounts.map(account => ({ label: `@${account.handle}`, value: account.id }))"
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
