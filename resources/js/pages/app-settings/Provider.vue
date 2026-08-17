<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppSettingsLayout from '@/layouts/AppSettingsLayout.vue'
import providerRoutes from '@/routes/app-settings/provider'

const props = defineProps<{
    providers: {
        name: string
        stored: boolean
        configured: boolean
        agents: string[]
    }[]
    labs: string[]
}>()

const selected = ref(props.providers[0]?.name ?? 'anthropic')
</script>

<template>
    <AppSettingsLayout title="AI provider">
        <Head title="AI provider" />

        <div class="max-w-3xl space-y-4">
            <UCard>
                <template #header>
                    <h2 class="font-medium">
                        Keys
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        Encrypted with <code>CREDENTIALS_KEY</code> and never
                        sent back to this page. A key set in the environment
                        keeps working until you save one here.
                    </p>
                </template>

                <div class="space-y-3">
                    <div
                        v-for="entry in providers"
                        :key="entry.name"
                        class="flex flex-wrap items-center gap-3 rounded-lg p-3 ring ring-default"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">
                                {{ entry.name }}
                            </p>
                            <p class="truncate text-sm text-muted">
                                {{ entry.agents.length
                                    ? `${entry.agents.length} agents: ${entry.agents.join(', ')}`
                                    : 'No agent runs on it right now.' }}
                            </p>
                        </div>

                        <UBadge
                            v-if="entry.stored"
                            color="success"
                            variant="subtle"
                            label="Key stored"
                        />
                        <UBadge
                            v-else-if="entry.configured"
                            color="neutral"
                            variant="subtle"
                            label="From the environment"
                        />
                        <UBadge
                            v-else
                            color="error"
                            variant="subtle"
                            label="No key"
                        />

                        <UButton
                            color="neutral"
                            variant="subtle"
                            size="xs"
                            icon="i-lucide-plug-zap"
                            label="Test"
                            :disabled="!entry.configured"
                            @click="router.post(providerRoutes.test.url(entry.name), {}, { preserveScroll: true })"
                        />

                        <UButton
                            v-if="entry.stored"
                            color="error"
                            variant="ghost"
                            size="xs"
                            icon="i-lucide-trash-2"
                            aria-label="Remove the stored key"
                            @click="router.delete(providerRoutes.destroy.url(entry.name), { preserveScroll: true })"
                        />
                    </div>
                </div>
            </UCard>

            <UCard>
                <template #header>
                    <h2 class="font-medium">
                        Save a key
                    </h2>
                </template>

                <Form
                    v-slot="{ errors, processing }"
                    v-bind="providerRoutes.update.form()"
                    reset-on-success
                    class="space-y-4"
                >
                    <UFormField
                        label="Provider"
                        name="provider"
                        :error="errors.provider"
                    >
                        <!-- USelect renders no native control, so the value
                             travels in a hidden input rather than twice. -->
                        <USelect
                            v-model="selected"
                            :items="labs"
                            class="w-64"
                        />
                        <input
                            type="hidden"
                            name="provider"
                            :value="selected"
                        >
                    </UFormField>

                    <UFormField
                        label="API key"
                        name="key"
                        :error="errors.key"
                    >
                        <UInput
                            name="key"
                            type="password"
                            placeholder="sk-…"
                            required
                            class="w-full max-w-md"
                        />
                    </UFormField>

                    <UButton
                        type="submit"
                        :loading="processing"
                        label="Save key"
                    />
                </Form>
            </UCard>
        </div>
    </AppSettingsLayout>
</template>
