<script setup lang="ts">
import { Form, Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { ref } from 'vue'
import LinkedinHeader from '@/components/LinkedinHeader.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import linkedinAccountRoutes from '@/routes/linkedin/account'
import linkedinPostRoutes from '@/routes/linkedin/posts'
import type { LinkedinPost } from '@/types'

defineOptions({ layout: AppLayout })

defineProps<{
    posts: LinkedinPost[]
    hasAccount: boolean
}>()

const page = usePage()
const toast = useToast()

// New drafts appear on their own schedule (the cadence tick), so this
// screen rereads rather than sitting still: `.ai/rules/js.md`'s reasoning for
// keeping this out of Settings.
usePoll(15000, { only: ['posts'] })

const editing = ref<number | null>(null)
const approving = ref<number | null>(null)
const rejecting = ref<number | null>(null)

const STATUS = {
    draft: { color: 'neutral' as const, label: 'Draft' },
    approved: { color: 'info' as const, label: 'Publishing…' },
    published: { color: 'success' as const, label: 'Published' },
    rejected: { color: 'neutral' as const, label: 'Rejected' },
    failed: { color: 'error' as const, label: 'Failed' }
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

function reject (post: LinkedinPost) {
    rejecting.value = post.id
    router.delete(linkedinPostRoutes.destroy.url(post.id), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Draft rejected', color: 'neutral' }),
        onFinish: () => rejecting.value = null
    })
}
</script>

<template>
    <Head title="LinkedIn posts" />

    <div class="space-y-4 p-6">
        <LinkedinHeader tab="posts" />

        <div>
            <h2 class="font-medium">
                LinkedIn posts
            </h2>
            <p class="text-sm text-muted">
                Every draft, from every source, lands here for review. Nothing
                publishes without your approval.
            </p>
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
                        :loading="rejecting === post.id"
                        @click="reject(post)"
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
        </div>

        <p
            v-if="!posts.length"
            class="rounded-lg p-6 text-sm text-muted ring ring-default"
        >
            No drafts yet.
        </p>
    </div>
</template>
