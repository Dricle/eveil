<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppSettingsLayout from '@/layouts/AppSettingsLayout.vue'
import billingRoutes from '@/routes/app-settings/billing'

const props = defineProps<{
    billing: {
        trial_credits: number
        trial_lead_limit: number
        credits_per_dollar: number
    }
}>()

// Edited in place: Nuxt UI inputs read `default-value` once at mount, so the
// draft has to be a plain ref patched on every prop change, same reason
// Limits.vue keeps one.
const draft = ref<Record<string, number>>({})

watch(() => props.billing, (billing) => {
    draft.value = Object.fromEntries(
        Object.entries(billing).map(([name, value]) => [name, Number(value)])
    )
}, { immediate: true, deep: true })
</script>

<template>
    <AppSettingsLayout title="Billing">
        <Head title="Billing" />

        <Form
            v-slot="{ errors, processing, recentlySuccessful }"
            v-bind="billingRoutes.update.form()"
            class="max-w-2xl space-y-4"
        >
            <UCard>
                <template #header>
                    <h2 class="font-medium">
                        Rate
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        Pay-as-you-go: a customer tops up whatever amount they choose, and
                        this is the whole pricing model, no plan tiers to keep in sync
                        with it.
                    </p>
                </template>

                <UFormField
                    label="Credits per dollar"
                    name="credits_per_dollar"
                    :error="errors.credits_per_dollar"
                >
                    <UInput
                        v-model="draft.credits_per_dollar"
                        name="credits_per_dollar"
                        type="number"
                        min="1"
                        required
                        class="w-full sm:w-64"
                    />
                </UFormField>
            </UCard>

            <UCard>
                <template #header>
                    <h2 class="font-medium">
                        Trial
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        What a new organization starts with, and the abuse guard on it:
                        a cap on leads discovered, separate from credit spend.
                    </p>
                </template>

                <div class="grid gap-4 sm:grid-cols-2">
                    <UFormField
                        label="Trial credits"
                        name="trial_credits"
                        :error="errors.trial_credits"
                    >
                        <UInput
                            v-model="draft.trial_credits"
                            name="trial_credits"
                            type="number"
                            min="0"
                            required
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        label="Trial lead limit"
                        name="trial_lead_limit"
                        :error="errors.trial_lead_limit"
                    >
                        <UInput
                            v-model="draft.trial_lead_limit"
                            name="trial_lead_limit"
                            type="number"
                            min="0"
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
