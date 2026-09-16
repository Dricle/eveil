<script setup lang="ts">
import { Form, Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import linkedinPostRoutes from '@/routes/linkedin/posts'
import linkedinAccountRoutes from '@/routes/settings/linkedin'
import type { LinkedinPost } from '@/types'

defineOptions({ layout: AppLayout })

const props = defineProps<{
    posts: LinkedinPost[]
    hasAccount: boolean
    currentProjectFrequency: 'off' | 'daily' | 'weekly' | 'biweekly' | 'monthly'
}>()

const page = usePage()
const toast = useToast()

// New drafts appear on their own schedule (the cadence tick), so this
// screen rereads rather than sitting still: `.ai/rules/js.md`'s reasoning for
// keeping this out of Settings.
usePoll(15000, { only: ['posts'] })

// A local draft synced from the prop, not `default-value`: Nuxt UI's select
// reads that once and every re-render (this page's own redirect, or the poll
// above) would silently stomp whatever the user just picked.
const frequency = ref(props.currentProjectFrequency)
watch(() => props.currentProjectFrequency, value => frequency.value = value, { immediate: true })

const FREQUENCIES = [
    { label: 'Off', value: 'off' },
    { label: 'Daily', value: 'daily' },
    { label: 'Weekly', value: 'weekly' },
    { label: 'Every two weeks', value: 'biweekly' },
    { label: 'Monthly', value: 'monthly' }
]

const editing = ref<number | null>(null)
const approving = ref<number | null>(null)
const rejecting = ref<number | null>(null)
const deleting = ref<number | null>(null)
const promoting = ref<number | null>(null)
const rejectingPost = ref<LinkedinPost | null>(null)
const rejectReason = ref('')

const STATUS = {
    draft: { color: 'neutral' as const, label: 'Draft' },
    published: { color: 'success' as const, label: 'Published' },
    rejected: { color: 'neutral' as const, label: 'Rejected' }
}

const SOURCE = {
    knowledge_base: 'Knowledge base',
    client_won: 'New client',
    news: 'Industry news',
    manual: 'Drafted with Evie'
}

function approve (post: LinkedinPost) {
    approving.value = post.id
    router.post(linkedinPostRoutes.approve.url(post.id), {}, {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Publishing to LinkedIn…', color: 'success' }),
        onFinish: () => approving.value = null
    })
}

function openReject (post: LinkedinPost) {
    rejectingPost.value = post
    rejectReason.value = ''
}

function confirmReject () {
    const post = rejectingPost.value
    if (!post) {
        return
    }

    rejecting.value = post.id
    router.post(linkedinPostRoutes.reject.url(post.id), { reason: rejectReason.value }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ title: 'Draft rejected', color: 'neutral' })
            rejectingPost.value = null
        },
        onFinish: () => rejecting.value = null
    })
}

function destroy (post: LinkedinPost) {
    deleting.value = post.id
    router.delete(linkedinPostRoutes.destroy.url(post.id), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Draft deleted', color: 'neutral' }),
        onFinish: () => deleting.value = null
    })
}

function promote (post: LinkedinPost) {
    promoting.value = post.id
    router.post(linkedinPostRoutes.promote.url(post.id), {}, {
        preserveScroll: true,
        onSuccess: () => toast.add({
            title: 'Marked as successful',
            description: 'Your writer will use this as an example for future posts on this project.',
            color: 'success'
        }),
        onFinish: () => promoting.value = null
    })
}
</script>

<template>
    <Head title="LinkedIn posts" />

    <div class="space-y-4 p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-medium">
                    LinkedIn posts
                </h2>
                <p class="text-sm text-muted">
                    Every draft, from every source, lands here for review. Nothing
                    publishes without your approval.
                </p>
            </div>

            <Form
                v-slot="{ processing }"
                v-bind="linkedinPostRoutes.cadence.form()"
                class="flex items-end gap-3"
            >
                <UFormField label="New post">
                    <USelect
                        v-model="frequency"
                        name="linkedin_post_frequency"
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
        </div>

        <UAlert
            v-if="!hasAccount"
            color="warning"
            variant="subtle"
            icon="i-lucide-plug"
            title="No LinkedIn account connected to this project"
            description="Drafts can still be written, but nothing can be approved until an account is connected."
            :actions="[{ label: 'Connect LinkedIn', to: linkedinAccountRoutes.index.url(), color: 'warning', variant: 'solid' }]"
        />

        <UAlert
            v-if="page.props.status"
            color="success"
            variant="subtle"
            icon="i-lucide-check"
            :description="String(page.props.status)"
        />

        <div
            v-for="post in posts"
            :key="post.id"
            class="space-y-3 rounded-lg p-4 ring ring-default"
        >
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

                <div class="ml-auto flex items-center gap-2">
                    <UButton
                        v-if="post.status === 'draft'"
                        icon="i-lucide-pencil"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        label="Edit"
                        @click="editing = editing === post.id ? null : post.id"
                    />
                    <UButton
                        v-if="post.status === 'draft'"
                        icon="i-lucide-check"
                        color="success"
                        variant="subtle"
                        size="xs"
                        label="Approve & publish"
                        :loading="approving === post.id"
                        :disabled="!hasAccount"
                        @click="approve(post)"
                    />
                    <UButton
                        v-if="post.status === 'draft'"
                        icon="i-lucide-x"
                        color="error"
                        variant="ghost"
                        size="xs"
                        label="Reject"
                        @click="openReject(post)"
                    />
                    <UButton
                        v-if="post.status === 'published' && !post.promoted_at"
                        icon="i-lucide-thumbs-up"
                        color="success"
                        variant="ghost"
                        size="xs"
                        label="Mark as successful"
                        :loading="promoting === post.id"
                        @click="promote(post)"
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
                        :loading="deleting === post.id"
                        @click="destroy(post)"
                    />
                </div>
            </div>

            <p class="text-sm text-dimmed">
                {{ post.evidence }}
            </p>

            <Form
                v-if="editing === post.id"
                v-slot="{ processing }"
                v-bind="linkedinPostRoutes.update.form(post.id)"
                class="space-y-2"
                @success="editing = null"
            >
                <UTextarea
                    name="body"
                    :default-value="post.body"
                    :rows="6"
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

            <a
                v-if="post.urn"
                :href="`https://www.linkedin.com/feed/update/${post.urn}/`"
                target="_blank"
                rel="noopener"
                class="text-sm text-primary"
            >View on LinkedIn</a>

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
        </div>

        <p
            v-if="!posts.length"
            class="rounded-lg p-6 text-sm text-muted ring ring-default"
        >
            No drafts yet.
        </p>

        <UModal
            :open="rejectingPost !== null"
            title="Reject this draft"
            @update:open="(value: boolean) => { if (!value) rejectingPost = null }"
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
                        placeholder="Too many emojis, wrong tone, ..."
                    />
                </UFormField>
            </template>

            <template #footer>
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Cancel"
                    @click="rejectingPost = null"
                />
                <UButton
                    color="error"
                    label="Reject"
                    :loading="rejecting === rejectingPost?.id"
                    @click="confirmReject"
                />
            </template>
        </UModal>
    </div>
</template>
