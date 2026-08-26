<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppSettingsLayout from '@/layouts/AppSettingsLayout.vue'
import emailExamples from '@/routes/app-settings/email-examples'
import type { EmailExampleRow } from '@/types'

const props = defineProps<{
    examples: EmailExampleRow[]
    thresholds: { min_sends: number, min_positive_rate: number, max_unsubscribe_rate: number }
}>()

const mode = ref<'paste' | 'upload'>('paste')

const draft = ref<Record<string, number>>({})

watch(() => props.thresholds, (thresholds) => {
    draft.value = Object.fromEntries(
        Object.entries(thresholds).map(([name, value]) => [name, Number(value)])
    )
}, { immediate: true, deep: true })

function sourceLabel (source: string): string {
    return source === 'campaign' ? 'Promoted' : 'Manual'
}
</script>

<template>
    <AppSettingsLayout title="Email examples">
        <Head title="Email examples" />

        <div class="max-w-2xl space-y-4">
            <UCard>
                <template #header>
                    <h2 class="font-medium">
                        Add an example
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        Fed back into every agent that writes an outreach email — a random
                        ten each time, as inspiration, never copied verbatim.
                    </p>
                </template>

                <div class="mb-4 flex gap-2">
                    <UButton
                        :variant="mode === 'paste' ? 'solid' : 'outline'"
                        color="neutral"
                        size="sm"
                        label="Paste text"
                        @click="mode = 'paste'"
                    />
                    <UButton
                        :variant="mode === 'upload' ? 'solid' : 'outline'"
                        color="neutral"
                        size="sm"
                        label="Upload .eml"
                        @click="mode = 'upload'"
                    />
                </div>

                <Form
                    v-slot="{ errors, processing, recentlySuccessful }"
                    v-bind="emailExamples.store.form()"
                    class="space-y-4"
                    reset-on-success
                >
                    <template v-if="mode === 'paste'">
                        <UFormField
                            label="Subject"
                            name="subject"
                            :error="errors.subject"
                        >
                            <UInput
                                name="subject"
                                required
                                class="w-full"
                            />
                        </UFormField>

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
                    </template>

                    <UFormField
                        v-else
                        label="Email file (.eml)"
                        name="file"
                        :error="errors.file"
                    >
                        <input
                            type="file"
                            name="file"
                            accept=".eml"
                            class="block w-full text-sm"
                        >
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
                        When a campaign step earns its place here
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        Checked daily. A step is promoted once it has been sent at least
                        this many times, with a positive-reply rate at or above the floor
                        and an unsubscribe rate at or below the ceiling — never from one
                        lucky reply.
                    </p>
                </template>

                <Form
                    v-slot="{ errors, processing, recentlySuccessful }"
                    v-bind="emailExamples.thresholds.form()"
                    class="space-y-4"
                >
                    <div class="grid gap-4 sm:grid-cols-3">
                        <UFormField
                            label="Minimum sends"
                            name="min_sends"
                            :error="errors.min_sends"
                        >
                            <UInput
                                v-model="draft.min_sends"
                                name="min_sends"
                                type="number"
                                min="1"
                                required
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            label="Minimum positive-reply rate"
                            name="min_positive_rate"
                            help="A fraction, e.g. 0.10 for 10%."
                            :error="errors.min_positive_rate"
                        >
                            <UInput
                                v-model="draft.min_positive_rate"
                                name="min_positive_rate"
                                type="number"
                                min="0.01"
                                max="1"
                                step="0.01"
                                required
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            label="Maximum unsubscribe rate"
                            name="max_unsubscribe_rate"
                            help="A fraction, e.g. 0.02 for 2%."
                            :error="errors.max_unsubscribe_rate"
                        >
                            <UInput
                                v-model="draft.max_unsubscribe_rate"
                                name="max_unsubscribe_rate"
                                type="number"
                                min="0"
                                max="1"
                                step="0.01"
                                required
                                class="w-full"
                            />
                        </UFormField>
                    </div>

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
                        class="flex items-start justify-between gap-3 rounded-lg bg-elevated p-3"
                    >
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="truncate font-medium">{{ example.subject }}</span>
                                <UBadge
                                    :color="example.source === 'campaign' ? 'primary' : 'neutral'"
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
                            @click="router.delete(emailExamples.destroy.url(example.id))"
                        />
                    </div>
                </div>
            </UCard>
        </div>
    </AppSettingsLayout>
</template>
