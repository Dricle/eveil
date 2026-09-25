<script setup lang="ts">
import { Form, Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import SocialPostCard from '@/components/SocialPostCard.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { relativeUrl } from '@/lib/utils'
import socialAccountRoutes from '@/routes/settings/social'
import socialPostRoutes from '@/routes/social/posts'
import type { SocialAccount, SocialPlatform, SocialPost } from '@/types'

defineOptions({ layout: AppLayout })

type Frequency = 'off' | 'daily' | 'weekly' | 'biweekly' | 'monthly'

const props = defineProps<{
    posts: SocialPost[]
    blueskyAccounts: SocialAccount[]
    frequencies: { x: Frequency, bluesky: Frequency }
}>()

const page = usePage()
const slug = computed(() => page.props.currentProject!.slug)

// New drafts appear on the cadence's own schedule, so this screen rereads.
usePoll(15000, { only: ['posts'] })

// Local drafts synced from the props, not `default-value`: see `.ai/rules/js.md`.
const xFrequency = ref(props.frequencies.x)
const blueskyFrequency = ref(props.frequencies.bluesky)
watch(() => props.frequencies, (value) => {
    xFrequency.value = value.x
    blueskyFrequency.value = value.bluesky
}, { immediate: true, deep: true })

const FREQUENCIES = [
    { label: 'Off', value: 'off' },
    { label: 'Daily', value: 'daily' },
    { label: 'Weekly', value: 'weekly' },
    { label: 'Every two weeks', value: 'biweekly' },
    { label: 'Monthly', value: 'monthly' }
]

const PLATFORMS = [
    { label: 'All', value: 'all' },
    { label: 'X', value: 'x' },
    { label: 'Bluesky', value: 'bluesky' }
]

const TABS = [
    { label: 'Drafts', value: 'draft' },
    { label: 'Published', value: 'published' },
    { label: 'Rejected', value: 'rejected' }
]

const activePlatform = ref('all')
const activeTab = ref('draft')
const filteredPosts = computed(() => props.posts.filter(post =>
    post.status === activeTab.value && (activePlatform.value === 'all' || post.platform === activePlatform.value)
))

const generating = ref<SocialPlatform | null>(null)

function generate (platform: SocialPlatform) {
    generating.value = platform
    router.post(socialPostRoutes.generate.url({ project: slug.value }), { platform }, {
        preserveScroll: true,
        onFinish: () => generating.value = null
    })
}
</script>

<template>
    <Head title="X & Bluesky posts" />

    <div class="space-y-4 p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-medium">
                    X & Bluesky posts
                </h2>
                <p class="text-sm text-muted">
                    Bluesky posts are published for you once approved. X posts you
                    copy and post yourself, then paste the link back here.
                </p>
            </div>

            <Form
                v-slot="{ processing }"
                v-bind="socialPostRoutes.cadence.form({ project: slug })"
                class="flex flex-wrap items-end gap-3"
            >
                <UFormField label="New X post">
                    <USelect
                        v-model="xFrequency"
                        name="x_post_frequency"
                        :items="FREQUENCIES"
                        class="w-40"
                    />
                </UFormField>

                <UFormField label="New Bluesky post">
                    <USelect
                        v-model="blueskyFrequency"
                        name="bluesky_post_frequency"
                        :items="FREQUENCIES"
                        class="w-40"
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
            v-if="!blueskyAccounts.length"
            color="neutral"
            variant="subtle"
            icon="i-lucide-plug"
            title="No Bluesky account connected to this project"
            description="Bluesky drafts can still be written, but nothing can be published until an account is connected. X needs no account."
            :actions="[{ label: 'Connect Bluesky', to: relativeUrl(socialAccountRoutes.index.url({ project: slug })), color: 'neutral', variant: 'solid' }]"
        />

        <UAlert
            v-if="page.props.status"
            color="success"
            variant="subtle"
            icon="i-lucide-check"
            :description="String(page.props.status)"
        />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <UTabs
                    v-model="activeTab"
                    :items="TABS"
                    :content="false"
                />
                <UTabs
                    v-model="activePlatform"
                    :items="PLATFORMS"
                    :content="false"
                    variant="link"
                />
            </div>

            <div class="flex gap-2">
                <UButton
                    icon="i-lucide-sparkles"
                    color="neutral"
                    variant="outline"
                    label="Write an X post"
                    :loading="generating === 'x'"
                    @click="generate('x')"
                />
                <UButton
                    icon="i-lucide-sparkles"
                    color="neutral"
                    variant="outline"
                    label="Write a Bluesky post"
                    :loading="generating === 'bluesky'"
                    @click="generate('bluesky')"
                />
            </div>
        </div>

        <SocialPostCard
            v-for="post in filteredPosts"
            :key="post.id"
            :post="post"
            :bluesky-accounts="blueskyAccounts"
        />

        <p
            v-if="!filteredPosts.length"
            class="rounded-lg p-6 text-sm text-muted ring ring-default"
        >
            No {{ activeTab }} posts.
        </p>
    </div>
</template>
