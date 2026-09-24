<script setup lang="ts">
import { Form, Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import RedditThreadCard from '@/components/RedditThreadCard.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { groupThreads } from '@/lib/reddit'
import redditReplyRoutes from '@/routes/reddit/replies'
import type { RedditReply } from '@/types'

defineOptions({ layout: AppLayout })

const props = defineProps<{
    replies: RedditReply[]
    scanFrequency: 'off' | 'daily' | 'weekly' | 'biweekly' | 'monthly'
}>()

const page = usePage()

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

const TABS = [
    { label: 'Drafts', value: 'draft' },
    { label: 'Posted', value: 'published' },
    { label: 'Rejected', value: 'rejected' }
]

const activeTab = ref('draft')

const threads = computed(() => groupThreads(props.replies.filter(reply => reply.status === activeTab.value)))

const scanning = ref(false)

function scan () {
    scanning.value = true
    router.post(redditReplyRoutes.scan.url({ project: page.props.currentProject!.slug }), {}, {
        preserveScroll: true,
        onFinish: () => scanning.value = false
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

        <UTabs
            v-model="activeTab"
            :items="TABS"
            :content="false"
        />

        <RedditThreadCard
            v-for="thread in threads"
            :key="thread.permalink"
            :thread="thread"
        />

        <p
            v-if="!threads.length"
            class="rounded-lg p-6 text-sm text-muted ring ring-default"
        >
            {{ activeTab === 'draft' ? 'No drafts yet. Turn on scanning above, or scan now.' : `No ${TABS.find(tab => tab.value === activeTab)!.label.toLowerCase()} replies.` }}
        </p>
    </div>
</template>
