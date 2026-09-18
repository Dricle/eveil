<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import AppSettingsLayout from '@/layouts/AppSettingsLayout.vue'
import redditReplyExamples from '@/routes/app-settings/reddit-reply-examples'
import type { RedditReplyExampleRow } from '@/types'

defineOptions({ layout: [AppLayout, [AppSettingsLayout, { title: 'Reddit reply examples' }]] })

const props = defineProps<{
    examples: RedditReplyExampleRow[]
    threshold: { min_score: number }
}>()

const minScore = ref(props.threshold.min_score)

watch(() => props.threshold, (threshold) => {
    minScore.value = threshold.min_score
}, { immediate: true })

function sourceLabel (source: string): string {
    return source === 'promoted' ? 'Promoted' : 'Manual'
}

const viewing = ref<RedditReplyExampleRow | null>(null)
</script>

<template>
    <Head title="Reddit reply examples" />

    <div class="space-y-4">
        <UCard>
            <template #header>
                <h2 class="font-medium">
                    Add an example
                </h2>
                <p class="mt-1 text-sm text-muted">
                    Fed back into every project's Reddit writer: a random
                    sample each time, as inspiration, never copied verbatim.
                    Instance-wide, shared across every tenant - see
                    <span class="font-medium">"When a reply earns its place here"</span>
                    for how one reaches this on its own.
                </p>
            </template>

            <Form
                v-slot="{ errors, processing, recentlySuccessful }"
                v-bind="redditReplyExamples.store.form()"
                class="space-y-4"
                reset-on-success
            >
                <UFormField
                    label="Body"
                    name="body"
                    :error="errors.body"
                >
                    <UTextarea
                        name="body"
                        :rows="6"
                        autoresize
                        required
                        class="w-full"
                    />
                </UFormField>

                <div class="flex items-center gap-3">
                    <UButton
                        type="submit"
                        :loading="processing"
                        label="Add"
                    />
                    <span
                        v-if="recentlySuccessful"
                        class="text-sm text-muted"
                    >Added.</span>
                </div>
            </Form>
        </UCard>

        <UCard>
            <template #header>
                <h2 class="font-medium">
                    When a reply earns its place here
                </h2>
                <p class="mt-1 text-sm text-muted">
                    Checked daily, only for a reply whose comment link was
                    pasted back after posting. It joins the bank once its
                    real score crosses this floor.
                </p>
            </template>

            <Form
                v-slot="{ errors, processing, recentlySuccessful }"
                v-bind="redditReplyExamples.threshold.form()"
                class="space-y-4"
            >
                <UFormField
                    label="Minimum score"
                    name="min_score"
                    :error="errors.min_score"
                    class="max-w-xs"
                >
                    <UInput
                        v-model="minScore"
                        name="min_score"
                        type="number"
                        min="1"
                        required
                        class="w-full"
                    />
                </UFormField>

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
            </Form>
        </UCard>

        <UCard>
            <template #header>
                <h2 class="font-medium">
                    The bank ({{ examples.length }})
                </h2>
            </template>

            <p
                v-if="!examples.length"
                class="text-sm text-muted"
            >
                Nothing in it yet.
            </p>

            <div
                v-else
                class="space-y-2"
            >
                <div
                    v-for="example in examples"
                    :key="example.id"
                    class="flex cursor-pointer items-start justify-between gap-3 rounded-lg bg-elevated p-3 hover:bg-elevated/70"
                    @click="viewing = example"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <UBadge
                                :color="example.source === 'promoted' ? 'primary' : 'neutral'"
                                variant="subtle"
                                size="sm"
                                :label="sourceLabel(example.source)"
                            />
                        </div>
                        <p class="mt-1 line-clamp-2 text-sm text-dimmed">
                            {{ example.body }}
                        </p>
                    </div>

                    <UButton
                        type="button"
                        color="error"
                        variant="ghost"
                        icon="i-lucide-trash-2"
                        size="sm"
                        @click.stop="router.delete(redditReplyExamples.destroy.url(example.id))"
                    />
                </div>
            </div>
        </UCard>
    </div>

    <UModal
        :open="viewing !== null"
        title="Reddit reply example"
        :ui="{ content: 'max-w-lg' }"
        @update:open="(value: boolean) => { if (!value) viewing = null }"
    >
        <template #body>
            <div
                v-if="viewing"
                class="space-y-3"
            >
                <div class="flex items-center gap-2">
                    <UBadge
                        :color="viewing.source === 'promoted' ? 'primary' : 'neutral'"
                        variant="subtle"
                        size="sm"
                        :label="sourceLabel(viewing.source)"
                    />
                    <span
                        v-if="viewing.added_by"
                        class="text-sm text-dimmed"
                    >Added by {{ viewing.added_by }}</span>
                </div>
                <p class="whitespace-pre-line text-sm">
                    {{ viewing.body }}
                </p>
            </div>
        </template>

        <template #footer>
            <UButton
                color="neutral"
                variant="ghost"
                label="Close"
                @click="viewing = null"
            />
        </template>
    </UModal>
</template>
