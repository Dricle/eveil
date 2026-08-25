<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppSettingsLayout from '@/layouts/AppSettingsLayout.vue'
import sendingRoutes from '@/routes/app-settings/sending'

const props = defineProps<{
    sending: Record<string, number>
}>()

const draft = ref<Record<string, number>>({})

watch(() => props.sending, (sending) => {
    draft.value = Object.fromEntries(
        Object.entries(sending).map(([name, value]) => [name, Number(value)])
    )
}, { immediate: true, deep: true })
</script>

<template>
    <AppSettingsLayout title="Sending">
        <Head title="Sending" />

        <Form
            v-slot="{ errors, processing, recentlySuccessful }"
            v-bind="sendingRoutes.update.form()"
            class="max-w-2xl space-y-4"
        >
            <UCard>
                <template #header>
                    <h2 class="font-medium">
                        Pace
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        Cold outreach dies from bursts. Nothing leaves outside the window,
                        whatever a mailbox's own timezone is.
                    </p>
                </template>

                <div class="grid gap-4 sm:grid-cols-2">
                    <UFormField
                        label="Window start (hour, local)"
                        name="window_start"
                        :error="errors.window_start"
                    >
                        <UInput
                            v-model="draft.window_start"
                            name="window_start"
                            type="number"
                            min="0"
                            max="23"
                            required
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        label="Window end (hour, local)"
                        name="window_end"
                        :error="errors.window_end"
                    >
                        <UInput
                            v-model="draft.window_end"
                            name="window_end"
                            type="number"
                            min="1"
                            max="24"
                            required
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        label="Minimum gap per mailbox (minutes)"
                        name="min_gap_minutes"
                        :error="errors.min_gap_minutes"
                    >
                        <UInput
                            v-model="draft.min_gap_minutes"
                            name="min_gap_minutes"
                            type="number"
                            min="1"
                            required
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        label="Bounce rate that pauses a mailbox"
                        name="max_bounce_rate"
                        :error="errors.max_bounce_rate"
                    >
                        <UInput
                            v-model="draft.max_bounce_rate"
                            name="max_bounce_rate"
                            type="number"
                            min="0.01"
                            max="1"
                            step="0.01"
                            required
                            class="w-full"
                        />
                    </UFormField>
                </div>
            </UCard>

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
    </AppSettingsLayout>
</template>
