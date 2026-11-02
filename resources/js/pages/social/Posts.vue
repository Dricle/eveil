<script setup lang="ts">
import { Form, Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import SocialPostCard from '@/components/SocialPostCard.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { PLATFORM_LABEL } from '@/lib/social'
import { relativeUrl } from '@/lib/utils'
import blueskyRoutes from '@/routes/settings/bluesky'
import instagramRoutes from '@/routes/settings/instagram'
import linkedinRoutes from '@/routes/settings/linkedin'
import socialPostRoutes from '@/routes/social/posts'
import type { SocialAccount, SocialPlatform, SocialPost } from '@/types'

defineOptions({ layout: AppLayout })

// One network's queue: LinkedIn, X, Bluesky and Instagram each have their own nav entry
// pointing here with their own `platform`.
const props = defineProps<{
    platform: SocialPlatform
    posts: SocialPost[]
    accounts: SocialAccount[]
    frequency: 'off' | 'daily' | 'weekly' | 'biweekly' | 'monthly'
    /** A post being written for this network right now, queued or running. */
    writing: boolean
}>()

const page = usePage()
const slug = computed(() => page.props.currentProject!.slug)
const label = computed(() => PLATFORM_LABEL[props.platform])

// New drafts appear on the cadence's own schedule, so this screen rereads,
// and much faster while something is being written or drawn so it shows up
// soon after it is done. `usePoll`'s interval is fixed: two polls, the fast
// one only running while there is something to wait for.
usePoll(15000, { only: ['posts', 'writing'] })
const working = computed(() => props.writing || props.posts.some(post => post.drawing_image || post.making_video))
const fastPoll = usePoll(4000, { only: ['posts', 'writing'] }, { autoStart: false })
watch(working, value => value ? fastPoll.start() : fastPoll.stop(), { immediate: true })

// A local draft synced from the prop, not `default-value`: see `.ai/rules/js.md`.
const frequency = ref(props.frequency)
watch(() => props.frequency, value => frequency.value = value, { immediate: true })

const FREQUENCIES = [
    { label: 'Off', value: 'off' },
    { label: 'Daily', value: 'daily' },
    { label: 'Weekly', value: 'weekly' },
    { label: 'Every two weeks', value: 'biweekly' },
    { label: 'Monthly', value: 'monthly' }
]

const TABS = [
    { label: 'Drafts', value: 'draft' },
    { label: 'Published', value: 'published' },
    { label: 'Rejected', value: 'rejected' }
]

const DESCRIPTION: Record<SocialPlatform, string> = {
    linkedin: 'Posts for your own LinkedIn profile, published through LinkedIn\'s official API once you approve them.',
    x: 'X posts you copy and post yourself, then paste the link back here: X charges for every post made through its API.',
    bluesky: 'Bluesky posts, published through Bluesky\'s own API once you approve them.',
    instagram: 'Instagram posts, each with an image Eveil generates, published once you approve them.'
}

// Where this network's account is connected. X has none.
const connectUrl = computed(() => ({
    linkedin: relativeUrl(linkedinRoutes.index.url({ project: slug.value })),
    x: null,
    bluesky: relativeUrl(blueskyRoutes.index.url({ project: slug.value })),
    instagram: relativeUrl(instagramRoutes.index.url({ project: slug.value }))
})[props.platform])

const activeTab = ref('draft')
const filteredPosts = computed(() => props.posts.filter(post => post.status === activeTab.value))

const generating = ref(false)

function generate () {
    generating.value = true
    router.post(socialPostRoutes.generate.url({ project: slug.value, platform: props.platform }), {}, {
        preserveScroll: true,
        onFinish: () => generating.value = false
    })
}
</script>

<template>
    <Head :title="`${label} posts`" />

    <div class="space-y-4 p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-medium">
                    {{ label }} posts
                </h2>
                <p class="text-sm text-muted">
                    {{ DESCRIPTION[platform] }}
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <Form
                    v-slot="{ processing }"
                    v-bind="socialPostRoutes.cadence.form({ project: slug, platform })"
                    class="flex items-end gap-3"
                >
                    <UFormField label="New post">
                        <USelect
                            v-model="frequency"
                            name="frequency"
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
                    icon="i-lucide-sparkles"
                    color="neutral"
                    variant="outline"
                    label="Write one now"
                    :loading="generating"
                    @click="generate"
                />
            </div>
        </div>

        <UAlert
            v-if="connectUrl && !accounts.length"
            color="warning"
            variant="subtle"
            icon="i-lucide-plug"
            :title="`No ${label} account connected to this project`"
            description="Drafts can still be written, but nothing can be published until an account is connected."
            :actions="[{ label: `Connect ${label}`, to: connectUrl, color: 'warning', variant: 'solid' }]"
        />

        <UAlert
            v-if="page.props.status"
            color="success"
            variant="subtle"
            icon="i-lucide-check"
            :description="String(page.props.status)"
        />

        <div
            v-if="writing"
            class="flex items-center gap-3 rounded-lg p-4 text-sm ring ring-default"
        >
            <UIcon
                name="i-lucide-loader-circle"
                class="size-4 animate-spin text-primary"
            />
            <span>
                Writing a {{ label }} post{{ platform === 'instagram' ? ' and drawing its image' : '' }}. It appears here on its own in a minute or two.
            </span>
        </div>

        <UTabs
            v-model="activeTab"
            :items="TABS"
            :content="false"
        />

        <SocialPostCard
            v-for="post in filteredPosts"
            :key="post.id"
            :post="post"
            :accounts="accounts"
        />

        <p
            v-if="!filteredPosts.length"
            class="rounded-lg p-6 text-sm text-muted ring ring-default"
        >
            No {{ activeTab }} posts.
        </p>
    </div>
</template>
