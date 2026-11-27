<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import launchRoutes from '@/routes/launch'

export type LaunchItem = {
    id: number
    state: 'todo' | 'posted' | 'dismissed'
    available_again_on: string | null
    draft: string | null
    drafting: boolean
    draft_error: string | null
    posts: { url: string, posted_at: string }[]
    venue: {
        name: string
        url: string
        kind: string
        origin: 'catalog' | 'user' | 'ai'
        is_paid: boolean
        price_note: string | null
        cooldown_days: number | null
        rules: string | null
    }
}

const props = defineProps<{ item: LaunchItem }>()

const page = usePage()
const toast = useToast()
const slug = computed(() => page.props.currentProject!.slug)
const args = computed(() => ({ project: slug.value, launch_item: props.item.id }))

const open = ref(false)
const draft = ref('')
watch(() => props.item.draft, value => draft.value = value ?? '', { immediate: true })

const postedUrl = ref('')
const postOpen = ref(false)
const busy = ref<string | null>(null)

const formatDate = (iso: string) => new Date(iso).toLocaleDateString()

function send (action: string, method: 'post' | 'patch' | 'delete', url: string, data: Record<string, string | boolean>, success: string) {
    busy.value = action
    router.visit(url, {
        method,
        data,
        preserveScroll: true,
        onSuccess: () => toast.add({ title: success, color: 'success' }),
        onFinish: () => busy.value = null
    })
}

function writeCopy () {
    open.value = true
    send('draft', 'post', launchRoutes.items.draft.url(args.value), {}, 'Writing the copy. It appears here in a moment.')
}

function saveDraft () {
    send('save', 'patch', launchRoutes.items.update.url(args.value), { draft: draft.value }, 'Draft saved')
}

function setDismissed (dismissed: boolean) {
    send('dismiss', 'patch', launchRoutes.items.update.url(args.value), { dismissed }, dismissed ? 'Dismissed' : 'Restored')
}

function markPosted () {
    busy.value = 'post'
    router.post(launchRoutes.items.submissions.store.url(args.value), { url: postedUrl.value }, {
        preserveScroll: true,
        onSuccess: () => {
            postOpen.value = false
            postedUrl.value = ''
            toast.add({ title: 'Marked as posted', color: 'success' })
        },
        onError: errors => toast.add({ title: errors.url ?? 'Could not save', color: 'error' }),
        onFinish: () => busy.value = null
    })
}

function remove () {
    send('remove', 'delete', launchRoutes.venues.destroy.url(args.value), {}, 'Venue removed')
}

async function copy () {
    try {
        await navigator.clipboard.writeText(draft.value)
        toast.add({ title: 'Copied', color: 'success' })
    } catch {
        toast.add({ title: 'Could not copy, select and copy manually', color: 'error' })
    }
}
</script>

<template>
    <div class="py-3">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <UButton
                :icon="open ? 'i-lucide-chevron-down' : 'i-lucide-chevron-right'"
                color="neutral"
                variant="ghost"
                size="xs"
                :aria-label="open ? 'Collapse' : 'Expand'"
                @click="open = !open"
            />

            <a
                :href="item.venue.url"
                target="_blank"
                rel="noopener"
                class="font-medium hover:underline"
            >{{ item.venue.name }}</a>

            <UBadge
                v-if="item.venue.origin === 'ai'"
                label="Found for you"
                color="primary"
                variant="subtle"
                size="sm"
            />
            <UBadge
                v-if="item.venue.origin === 'user'"
                label="Added by you"
                color="neutral"
                variant="subtle"
                size="sm"
            />
            <UBadge
                v-if="item.venue.is_paid"
                label="Paid"
                color="warning"
                variant="subtle"
                size="sm"
            />
            <span
                v-if="item.venue.price_note"
                class="text-xs text-muted"
            >{{ item.venue.price_note }}</span>
            <span
                v-if="item.state === 'posted' && item.available_again_on"
                class="text-xs text-muted"
            >Can post again on {{ formatDate(item.available_again_on) }}</span>
            <span
                v-if="item.state === 'todo' && item.posts.length"
                class="text-xs text-muted"
            >Posted {{ formatDate(item.posts[0].posted_at) }}, ready for another post</span>
            <UIcon
                v-if="item.drafting"
                name="i-lucide-loader-circle"
                class="size-4 animate-spin text-muted"
            />

            <div class="ml-auto flex items-center gap-2">
                <template v-if="item.state !== 'dismissed'">
                    <UButton
                        :label="item.draft ? 'Rewrite copy' : 'Write copy'"
                        icon="i-lucide-sparkles"
                        color="neutral"
                        variant="subtle"
                        size="sm"
                        :loading="busy === 'draft' || item.drafting"
                        @click="writeCopy"
                    />

                    <UPopover
                        v-if="item.state === 'todo'"
                        v-model:open="postOpen"
                    >
                        <UButton
                            label="Mark posted"
                            icon="i-lucide-check"
                            size="sm"
                        />

                        <template #content>
                            <form
                                class="flex w-80 flex-col gap-2 p-3"
                                @submit.prevent="markPosted"
                            >
                                <UFormField
                                    label="Link to your post"
                                    help="Paste the address of the post or listing, so you can find it again."
                                >
                                    <UInput
                                        v-model="postedUrl"
                                        type="url"
                                        required
                                        placeholder="https://"
                                        class="w-full"
                                    />
                                </UFormField>
                                <UButton
                                    type="submit"
                                    label="Save"
                                    :loading="busy === 'post'"
                                    class="self-end"
                                />
                            </form>
                        </template>
                    </UPopover>

                    <UButton
                        v-if="item.venue.origin === 'user'"
                        icon="i-lucide-trash-2"
                        color="neutral"
                        variant="ghost"
                        size="sm"
                        aria-label="Remove"
                        :loading="busy === 'remove'"
                        @click="remove"
                    />
                    <UButton
                        v-else-if="item.state === 'todo'"
                        label="Dismiss"
                        color="neutral"
                        variant="ghost"
                        size="sm"
                        :loading="busy === 'dismiss'"
                        @click="setDismissed(true)"
                    />
                </template>

                <UButton
                    v-else
                    label="Restore"
                    color="neutral"
                    variant="subtle"
                    size="sm"
                    :loading="busy === 'dismiss'"
                    @click="setDismissed(false)"
                />
            </div>
        </div>

        <div
            v-if="open"
            class="mt-3 space-y-3 pl-9"
        >
            <p
                v-if="item.venue.rules"
                class="text-sm text-muted"
            >
                {{ item.venue.rules }}
            </p>

            <UAlert
                v-if="item.draft_error"
                color="error"
                variant="subtle"
                title="The copy could not be written"
                :description="item.draft_error"
            />

            <template v-if="item.draft !== null || draft">
                <UTextarea
                    v-model="draft"
                    :rows="10"
                    autoresize
                    class="w-full"
                />
                <div class="flex gap-2">
                    <UButton
                        label="Copy"
                        icon="i-lucide-copy"
                        color="neutral"
                        variant="subtle"
                        size="sm"
                        @click="copy"
                    />
                    <UButton
                        v-if="draft !== (item.draft ?? '')"
                        label="Save edit"
                        size="sm"
                        :loading="busy === 'save'"
                        @click="saveDraft"
                    />
                </div>
            </template>
            <p
                v-else-if="!item.drafting"
                class="text-sm text-muted"
            >
                No copy yet. "Write copy" drafts what to paste into this venue's form.
            </p>

            <div
                v-if="item.posts.length"
                class="text-sm"
            >
                <p class="font-medium">
                    Posts
                </p>
                <ul class="mt-1 space-y-1">
                    <li
                        v-for="post in item.posts"
                        :key="post.url + post.posted_at"
                    >
                        <span class="text-muted">{{ formatDate(post.posted_at) }}</span>
                        <a
                            :href="post.url"
                            target="_blank"
                            rel="noopener"
                            class="ml-2 hover:underline"
                        >{{ post.url }}</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
