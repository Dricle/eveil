<script setup lang="ts">
import { Form, router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import linkedinPostRoutes from '@/routes/linkedin/posts'
import type { LinkedinAccount, LinkedinPost } from '@/types'

// One post with every action on it. Shared by `linkedin/Posts.vue` and the
// dashboard's to-review modal, so both review a draft exactly the same way.
const props = defineProps<{
    post: LinkedinPost
    linkedinAccounts: LinkedinAccount[]
}>()

const page = usePage()
const toast = useToast()

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

function toggleEdit () {
    editing.value = !editing.value
    editBody.value = props.post.body
}

function approve () {
    // One account: nothing to pick, approve goes straight through. Several:
    // ask which one, since the project may be granted more than one.
    if (props.linkedinAccounts.length > 1) {
        pickingAccount.value = true
        selectedAccountId.value = props.post.linkedin_account?.id ?? props.linkedinAccounts[0].id
        return
    }

    submitApprove(props.linkedinAccounts[0]?.id ?? null)
}

function confirmApprove () {
    if (!selectedAccountId.value) {
        return
    }

    submitApprove(selectedAccountId.value)
    pickingAccount.value = false
}

function submitApprove (linkedinAccountId: number | null) {
    if (!linkedinAccountId) {
        return
    }

    approving.value = true
    router.post(linkedinPostRoutes.approve.url({ project: page.props.currentProject!.slug, linkedin_post: props.post.id }), { linkedin_account_id: linkedinAccountId }, {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Publishing to LinkedIn…', color: 'success' }),
        onFinish: () => approving.value = false
    })
}

function openReject () {
    rejectOpen.value = true
    rejectReason.value = ''
}

function confirmReject () {
    rejecting.value = true
    router.post(linkedinPostRoutes.reject.url({ project: page.props.currentProject!.slug, linkedin_post: props.post.id }), { reason: rejectReason.value }, {
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
    router.delete(linkedinPostRoutes.destroy.url({ project: page.props.currentProject!.slug, linkedin_post: props.post.id }), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Draft deleted', color: 'neutral' }),
        onFinish: () => deleting.value = false
    })
}

function promote () {
    promoting.value = true
    router.post(linkedinPostRoutes.promote.url({ project: page.props.currentProject!.slug, linkedin_post: props.post.id }), {}, {
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
                v-if="post.linkedin_account && linkedinAccounts.length > 1"
                color="neutral"
                variant="outline"
                icon="i-lucide-linkedin"
                :label="post.linkedin_account.display_name"
            />

            <div class="ml-auto flex items-center gap-2">
                <UButton
                    v-if="post.status === 'draft'"
                    icon="i-lucide-pencil"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    label="Edit"
                    @click="toggleEdit"
                />
                <UButton
                    v-if="post.status === 'draft'"
                    icon="i-lucide-check"
                    color="success"
                    variant="subtle"
                    size="xs"
                    label="Approve & publish"
                    :loading="approving"
                    :disabled="!linkedinAccounts.length"
                    @click="approve"
                />
                <UButton
                    v-if="post.status === 'draft'"
                    icon="i-lucide-x"
                    color="error"
                    variant="ghost"
                    size="xs"
                    label="Reject"
                    @click="openReject"
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
            v-bind="linkedinPostRoutes.update.form({ project: page.props.currentProject!.slug, linkedin_post: post.id })"
            class="space-y-2"
            :options="{ preserveScroll: true }"
            @success="editing = false"
        >
            <UTextarea
                v-model="editBody"
                name="body"
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
                        placeholder="Too many emojis, wrong tone, ..."
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
                <UFormField label="LinkedIn account">
                    <USelect
                        v-model="selectedAccountId"
                        :items="linkedinAccounts.map(account => ({ label: account.display_name, value: account.id }))"
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
                    @click="confirmApprove"
                />
            </template>
        </UModal>
    </div>
</template>
