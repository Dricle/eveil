<script setup lang="ts">
import { Form, Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import ArticleCard from '@/components/ArticleCard.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import articleRoutes from '@/routes/seo/articles'
import insightRoutes from '@/routes/insights'
import { relativeUrl } from '@/lib/utils'
import type { Article } from '@/types'

defineOptions({ layout: AppLayout })

const props = defineProps<{
    articles: Article[]
    ideaCount: number
    frequency: 'off' | 'daily' | 'weekly' | 'biweekly' | 'monthly'
}>()

const page = usePage()

// Articles appear on their own schedule (the cadence, "Write one now", or
// Evie), so this screen rereads rather than sitting still, same as the
// LinkedIn and Reddit queues.
usePoll(15000, { only: ['articles', 'ideaCount'] })

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

        <!-- The ideas themselves are triaged in Insights, next to the rest
             of what the Reddit scan notices; the cadence still picks from
             the most asked. -->
        <UAlert
            v-if="ideaCount"
            color="neutral"
            variant="subtle"
            icon="i-lucide-lightbulb"
            :title="`${ideaCount} article ${ideaCount === 1 ? 'topic' : 'topics'} from Reddit`"
            description="Questions people keep asking, grouped and ranked by how many threads asked. The scheduled articles start from the most asked; you can also pick one to write now."
            :actions="[{ label: 'Open in Insights', color: 'neutral', variant: 'outline', trailingIcon: 'i-lucide-arrow-right', to: relativeUrl(insightRoutes.index.url({ project: page.props.currentProject!.slug }, { query: { kind: 'article' } })) }]"
        />

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
