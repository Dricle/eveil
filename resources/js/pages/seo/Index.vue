<script setup lang="ts">
import { Form, Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import ArticleCard from '@/components/ArticleCard.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import articleRoutes from '@/routes/seo/articles'
import ideaRoutes from '@/routes/seo/ideas'
import type { Article, ArticleIdea } from '@/types'

defineOptions({ layout: AppLayout })

const props = defineProps<{
    articles: Article[]
    ideas: ArticleIdea[]
    frequency: 'off' | 'daily' | 'weekly' | 'biweekly' | 'monthly'
}>()

const page = usePage()
const toast = useToast()

// Articles appear on their own schedule (the cadence, "Write one now", or
// Evie), so this screen rereads rather than sitting still, same as the
// LinkedIn and Reddit queues.
usePoll(15000, { only: ['articles', 'ideas'] })

const cadence = ref(props.frequency)
watch(() => props.frequency, value => cadence.value = value, { immediate: true })

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
const filtered = computed(() => props.articles.filter(article => article.status === activeTab.value))

// Held per idea: several can be clicked in a row while the first one's
// request is still in flight.
const writingIdea = ref<number | null>(null)
const dismissingIdea = ref<number | null>(null)

function writeIdea (idea: ArticleIdea) {
    writingIdea.value = idea.id
    router.post(ideaRoutes.write.url({ project: page.props.currentProject!.slug, idea: idea.id }), {}, {
        preserveScroll: true,
        onFinish: () => writingIdea.value = null
    })
}

function dismissIdea (idea: ArticleIdea) {
    dismissingIdea.value = idea.id
    router.post(ideaRoutes.dismiss.url({ project: page.props.currentProject!.slug, idea: idea.id }), {}, {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Idea dismissed', color: 'neutral' }),
        onFinish: () => dismissingIdea.value = null
    })
}

const generating = ref(false)

function generate () {
    generating.value = true
    router.post(articleRoutes.generate.url({ project: page.props.currentProject!.slug }), {}, {
        preserveScroll: true,
        onFinish: () => generating.value = false
    })
}
</script>

<template>
    <Head title="SEO" />

    <div class="space-y-4 p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-medium">
                    SEO articles
                </h2>
                <p class="text-sm text-muted">
                    Articles for your own blog, on what your buyers search for and
                    your site does not answer yet. Copy one into your blog, then
                    mark it published with the address it went live at.
                </p>
            </div>

            <div class="flex items-end gap-3">
                <Form
                    v-slot="{ processing }"
                    v-bind="articleRoutes.cadence.form({ project: page.props.currentProject!.slug })"
                    class="flex items-end gap-3"
                >
                    <UFormField label="New article">
                        <USelect
                            v-model="cadence"
                            name="article_frequency"
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
                    icon="i-lucide-pen-line"
                    color="neutral"
                    variant="subtle"
                    label="Write one now"
                    :loading="generating"
                    @click="generate"
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

        <!-- Noted by the Reddit scan while it looks for threads to reply to:
             a discussion worth an article, whether or not it is worth a
             reply. The cadence picks from these too. -->
        <UCard
            v-if="ideas.length"
            variant="subtle"
            :ui="{ body: 'p-1.5 sm:p-1.5' }"
        >
            <template #header>
                <h3 class="text-sm font-semibold">
                    Article ideas from Reddit
                </h3>
                <p class="mt-0.5 text-xs text-muted">
                    Discussions worth an article on your blog, noticed while scanning Reddit.
                </p>
            </template>

            <div
                v-for="idea in ideas"
                :key="idea.id"
                class="flex flex-wrap items-start gap-3 rounded-lg px-2.5 py-2"
            >
                <UIcon
                    name="i-lucide-lightbulb"
                    class="mt-0.5 size-4 shrink-0 text-muted"
                />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-highlighted">
                        {{ idea.angle }}
                    </p>
                    <a
                        :href="idea.source_ref"
                        target="_blank"
                        rel="noopener"
                        class="text-xs text-primary"
                    >{{ idea.title }} ↗</a>
                </div>
                <div class="flex shrink-0 gap-1.5">
                    <UButton
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        label="Dismiss"
                        :loading="dismissingIdea === idea.id"
                        @click="dismissIdea(idea)"
                    />
                    <UButton
                        icon="i-lucide-pen-line"
                        color="primary"
                        variant="subtle"
                        size="xs"
                        label="Write it"
                        :loading="writingIdea === idea.id"
                        @click="writeIdea(idea)"
                    />
                </div>
            </div>
        </UCard>

        <UTabs
            v-model="activeTab"
            :items="TABS"
            :content="false"
        />

        <ArticleCard
            v-for="article in filtered"
            :key="article.id"
            :article="article"
        />

        <p
            v-if="!filtered.length"
            class="rounded-lg p-6 text-sm text-muted ring ring-default"
        >
            {{ activeTab === 'draft' ? 'No drafts yet. Pick how often above, or write one now.' : `No ${activeTab} articles.` }}
        </p>
    </div>
</template>
