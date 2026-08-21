<script setup lang="ts">
import { Form, Head, usePoll } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import OpenQuestions from '@/components/OpenQuestions.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import knowledgeBase from '@/routes/settings/knowledge-base'
import type { ProjectDetail } from '@/types'

const props = defineProps<{ project: ProjectDetail }>()

const analysing = computed(() => props.project.last_analysis?.running === true)

// Only while a crawl is out. Nothing else on this page changes on its own.
const poll = usePoll(3000, { only: ['project'] }, { autoStart: analysing.value })

watch(analysing, busy => busy ? poll.start() : poll.stop())

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
                                    :name="field.name"
                                    :default-value="project.knowledge_base[field.name]"
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
                                    :name="field.name"
                                    :default-value="lines(field.name)"
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
