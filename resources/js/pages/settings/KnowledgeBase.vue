<script setup lang="ts">
import { Form, Head, router, usePoll } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import OpenQuestions from '@/components/OpenQuestions.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import knowledgeBase from '@/routes/settings/knowledge-base'
import repositories from '@/routes/settings/repositories'
import type { ProjectDetail } from '@/types'

const props = defineProps<{ project: ProjectDetail }>()

const repoUrl = ref('')

const analysing = computed(() => props.project.last_analysis?.running === true)

// Any repo still reading, not just the site: the same "reading X" banner
// language, shown for whichever kind is actually in flight.
const analysingRepo = computed(() =>
    props.project.code_repositories.some(repo => repo.last_analysis?.running === true))

// Only while a crawl or a repo read is out. Nothing else on this page
// changes on its own.
const busy = computed(() => analysing.value || analysingRepo.value)
const poll = usePoll(3000, { only: ['project'] }, { autoStart: busy.value })

watch(busy, isBusy => isBusy ? poll.start() : poll.stop())

const TEXTS = [
    { name: 'what_it_does', label: 'What it does', help: 'What the product is, and the problem it removes.' },
    { name: 'who_it_is_for', label: 'Who it is for', help: 'The kind of company, and the kind of person who buys it.' },
    { name: 'value_proposition', label: 'Value proposition', help: 'The single strongest reason someone switches to it.' },
    { name: 'positioning', label: 'Positioning', help: 'How it frames itself against the alternatives, including doing nothing.' },
    { name: 'pricing_model', label: 'Pricing model', help: 'How it charges, as stated on the site.' }
] as const

const LISTS = [
    { name: 'key_features', label: 'Key features' },
    { name: 'competitors', label: 'Competitors' },
    { name: 'proof_points', label: 'Proof points' }
] as const

// The list fields go over the wire one item per line; the server splits them.
function lines (field: typeof LISTS[number]['name']): string {
    return (props.project.knowledge_base?.[field] ?? []).join('\n')
}

// Every box is bound, never left to `default-value`: Nuxt UI reads that prop
// once at mount, and Vue then patches a form element's value against what the
// DOM holds, so every later render writes the frozen first value back over what
// was typed. This page polls every three seconds while a crawl is out, so
// without this it would wipe what somebody is typing three times a minute.
const draft = ref<Record<string, string>>({})

function fill () {
    draft.value = {
        ...Object.fromEntries(TEXTS.map(field => [field.name, props.project.knowledge_base?.[field.name] ?? ''])),
        ...Object.fromEntries(LISTS.map(field => [field.name, lines(field.name)]))
    }
}

watch(() => props.project, fill, { immediate: true, deep: true })
</script>

<template>
    <SettingsLayout title="Project knowledge">
        <Head title="Project knowledge" />

        <div class="max-w-2xl space-y-4">
            <UAlert
                v-if="project.last_analysis?.status === 'failed'"
                color="error"
                variant="subtle"
                icon="i-lucide-triangle-alert"
                title="The site could not be read"
                :description="project.last_analysis.error ?? undefined"
            />

            <UAlert
                v-else-if="analysing"
                color="neutral"
                variant="subtle"
                icon="i-lucide-loader"
                :ui="{ icon: 'animate-spin' }"
                title="Reading the site"
                :description="`${project.last_analysis?.pages_read ?? 0} of up to ${project.last_analysis?.pages_planned ?? 0} pages read. The portrait appears here once the model has seen them.`"
            />

            <UAlert
                v-else-if="!project.analyzed"
                color="neutral"
                variant="subtle"
                icon="i-lucide-loader"
                title="Reading the site"
                description="The portrait appears here once the analysis finishes."
            />

            <!-- A crawl that lost pages still produces a portrait. Saying which
                 pages are missing is what separates a thin summary from a
                 wrong one. -->
            <UAlert
                v-if="project.last_analysis?.failures.length"
                color="warning"
                variant="subtle"
                icon="i-lucide-file-warning"
                :title="`${project.last_analysis.failures.length} page(s) could not be read`"
            >
                <template #description>
                    <ul class="mt-1 space-y-1">
                        <li
                            v-for="failure in project.last_analysis.failures"
                            :key="failure.url"
                            class="truncate"
                        >
                            <span class="text-dimmed">{{ failure.url }}</span>: {{ failure.reason }}
                        </li>
                    </ul>
                </template>
            </UAlert>

            <OpenQuestions
                :questions="project.open_questions"
                title="Open questions"
            />

            <UCard>
                <template #header>
                    <h2 class="font-medium">
                        Code repositories
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        Source often names capabilities the site never mentions, or
                        contradicts something it oversells. GitHub only, for now.
                        "Deep analysis" lets the model roam the repo itself instead of
                        reading a handful of fixed files — slower and more expensive,
                        worth it for a repo the quick read left thin.
                    </p>
                </template>

                <div
                    v-if="!project.code_repositories.length"
                    class="text-sm text-muted"
                >
                    No repository linked yet.
                </div>

                <div
                    v-else
                    class="space-y-2"
                >
                    <div
                        v-for="repo in project.code_repositories"
                        :key="repo.id"
                        class="flex items-center justify-between gap-3 rounded-lg bg-elevated p-3 text-sm"
                    >
                        <div class="min-w-0">
                            <a
                                :href="repo.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="truncate font-medium hover:underline"
                            >{{ repo.name }}</a>
                            <p class="text-xs text-dimmed">
                                <template v-if="repo.last_analysis?.running && repo.last_analysis.type === 'repo_deep'">
                                    Exploring… {{ repo.last_analysis.pages_read }} file(s) read so far
                                </template>
                                <template v-else-if="repo.last_analysis?.running">
                                    Reading… {{ repo.last_analysis.pages_read }} of up to
                                    {{ repo.last_analysis.pages_planned }} files
                                </template>
                                <template v-else-if="repo.last_analysis?.status === 'failed'">
                                    {{ repo.last_analysis.error ?? 'Could not be read.' }}
                                </template>
                                <template v-else-if="repo.last_analysis?.type === 'repo_deep'">
                                    Explored deeply, {{ repo.last_analysis.pages_read }} file(s) read.
                                </template>
                                <template v-else-if="repo.last_analysis">
                                    Read successfully.
                                </template>
                                <template v-else>
                                    Not read yet.
                                </template>
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <UButton
                                type="button"
                                color="neutral"
                                variant="ghost"
                                icon="i-lucide-telescope"
                                size="sm"
                                label="Deep analysis"
                                :loading="repo.last_analysis?.running === true"
                                :disabled="repo.last_analysis?.running === true"
                                @click="router.post(repositories.explore.url(repo.id))"
                            />
                            <UButton
                                type="button"
                                color="error"
                                variant="ghost"
                                icon="i-lucide-trash-2"
                                size="sm"
                                @click="router.delete(repositories.destroy.url(repo.id))"
                            />
                        </div>
                    </div>
                </div>

                <template #footer>
                    <Form
                        v-slot="{ errors, processing }"
                        v-bind="repositories.store.form()"
                        class="flex items-start gap-3"
                        @success="repoUrl = ''"
                    >
                        <UFormField
                            class="flex-1"
                            name="url"
                            :error="errors.url"
                        >
                            <UInput
                                v-model="repoUrl"
                                name="url"
                                placeholder="https://github.com/owner/repo"
                                class="w-full"
                            />
                        </UFormField>
                        <UButton
                            type="submit"
                            :loading="processing"
                            label="Link"
                        />
                    </Form>
                </template>
            </UCard>

            <UCard v-if="project.knowledge_base?.recommendations?.length">
                <template #header>
                    <h2 class="font-medium">
                        Acquisition ideas
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        Levers the product is missing, each grounded in something specific
                        the portrait or the repo actually shows.
                    </p>
                </template>

                <div class="space-y-3">
                    <div
                        v-for="idea in project.knowledge_base.recommendations"
                        :key="idea.key"
                        class="rounded-lg bg-elevated p-3"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium">
                                {{ idea.idea }}
                            </p>
                            <div class="flex shrink-0 gap-1.5">
                                <UBadge
                                    :color="idea.impact === 'high' ? 'success' : idea.impact === 'medium' ? 'warning' : 'neutral'"
                                    variant="subtle"
                                    size="sm"
                                    :label="`${idea.impact} impact`"
                                />
                                <UBadge
                                    :color="idea.effort === 'low' ? 'success' : idea.effort === 'medium' ? 'warning' : 'neutral'"
                                    variant="subtle"
                                    size="sm"
                                    :label="`${idea.effort} effort`"
                                />
                            </div>
                        </div>
                        <p class="mt-1 text-sm text-muted">
                            {{ idea.evidence }}
                        </p>
                    </div>
                </div>
            </UCard>

            <template v-if="project.knowledge_base">
                <div class="flex flex-wrap items-center gap-2 text-sm text-muted">
                    <UBadge
                        v-if="project.edited_by_user"
                        color="primary"
                        variant="subtle"
                        icon="i-lucide-pencil"
                        label="Edited by you"
                    />
                    <span v-if="project.edited_by_user">
                        A later analysis will not overwrite this.
                    </span>
                    <span v-else-if="project.knowledge_base.confidence !== undefined">
                        Written from {{ project.last_analysis?.pages_read ?? 0 }} pages,
                        confidence {{ project.knowledge_base.confidence }}/100.
                    </span>
                </div>

                <Form
                    v-slot="{ errors, processing, recentlySuccessful }"
                    v-bind="knowledgeBase.update.form()"
                    class="space-y-4"
                >
                    <UCard>
                        <div class="space-y-4">
                            <UFormField
                                v-for="field in TEXTS"
                                :key="field.name"
                                :label="field.label"
                                :name="field.name"
                                :help="field.help"
                                :error="errors[field.name]"
                            >
                                <UTextarea
                                    v-model="draft[field.name]"
                                    :name="field.name"
                                    :rows="3"
                                    autoresize
                                    class="w-full"
                                />
                            </UFormField>

                            <UFormField
                                v-for="field in LISTS"
                                :key="field.name"
                                :label="field.label"
                                :name="field.name"
                                help="One per line."
                                :error="errors[field.name]"
                            >
                                <UTextarea
                                    v-model="draft[field.name]"
                                    :name="field.name"
                                    :rows="3"
                                    autoresize
                                    class="w-full"
                                />
                            </UFormField>
                        </div>

                        <template #footer>
                            <div class="flex items-center gap-3">
                                <UButton
                                    type="submit"
                                    :loading="processing"
                                    label="Save"
                                />
                                <span
                                    v-if="recentlySuccessful"
                                    class="text-sm text-muted"
                                >Saved.</span>
                            </div>
                        </template>
                    </UCard>
                </Form>
            </template>
        </div>
    </SettingsLayout>
</template>
