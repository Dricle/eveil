<script setup lang="ts">
import { Form, Head, usePage, usePoll } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import LinkedinPostCard from '@/components/LinkedinPostCard.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { relativeUrl } from '@/lib/utils'
import linkedinPostRoutes from '@/routes/linkedin/posts'
import linkedinAccountRoutes from '@/routes/settings/linkedin'
import type { LinkedinAccount, LinkedinPost } from '@/types'

defineOptions({ layout: AppLayout })

const props = defineProps<{
    posts: LinkedinPost[]
    linkedinAccounts: LinkedinAccount[]
    hasAccount: boolean
    currentProjectFrequency: 'off' | 'daily' | 'weekly' | 'biweekly' | 'monthly'
}>()

const page = usePage()

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

const TABS = [
    { label: 'Drafts', value: 'draft' },
    { label: 'Published', value: 'published' },
    { label: 'Rejected', value: 'rejected' }
]

const activeTab = ref('draft')
const filteredPosts = computed(() => props.posts.filter(post => post.status === activeTab.value))
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
                v-bind="linkedinPostRoutes.cadence.form({ project: page.props.currentProject!.slug })"
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
            :actions="[{ label: 'Connect LinkedIn', to: relativeUrl(linkedinAccountRoutes.index.url({ project: page.props.currentProject!.slug })), color: 'warning', variant: 'solid' }]"
        />

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

        <LinkedinPostCard
            v-for="post in filteredPosts"
            :key="post.id"
            :post="post"
            :linkedin-accounts="linkedinAccounts"
        />

        <p
            v-if="!filteredPosts.length"
            class="rounded-lg p-6 text-sm text-muted ring ring-default"
        >
            No {{ activeTab }} posts.
        </p>
    </div>
</template>
