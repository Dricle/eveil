<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import AppSettingsLayout from '@/layouts/AppSettingsLayout.vue'
import providerRoutes from '@/routes/app-settings/provider'

defineOptions({ layout: [AppLayout, [AppSettingsLayout, { title: 'AI provider' }]] })

const props = defineProps<{
    providers: {
        name: string
        keys: string[]
        configured: boolean
        agents: string[]
    }[]
    labs: string[]
}>()

const selected = ref(props.providers[0]?.name ?? 'anthropic')
</script>

<template>
    <Head title="AI provider" />

    <div class="space-y-4">
        <UCard>
            <template #header>
                <h2 class="font-medium">
                    Keys
                </h2>
                <p class="mt-1 text-sm text-muted">
                    Encrypted with <code>CREDENTIALS_KEY</code> and never
                    sent back to this page. Several keys can be stored per
                    provider; each call picks one at random. A key set in
                    the environment keeps working until you save one here.
                </p>
            </template>

            <div class="space-y-3">
                <div
                    v-for="entry in providers"
                    :key="entry.name"
                    class="rounded-lg p-3 ring ring-default"
                >
                    <div class="flex flex-wrap items-center gap-3">
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
                            v-if="entry.keys.length > 0"
                            color="success"
                            variant="subtle"
                            :label="entry.keys.length === 1 ? '1 key stored' : `${entry.keys.length} keys stored`"
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
                    </div>

                    <ul
                        v-if="entry.keys.length > 0"
                        class="mt-2 space-y-1"
                    >
                        <li
                            v-for="(keyName, index) in entry.keys"
                            :key="index"
                            class="flex items-center justify-between gap-2 rounded px-2 py-1 text-sm text-muted ring ring-default"
                        >
                            <span>{{ keyName }}</span>

                            <UButton
                                color="error"
                                variant="ghost"
                                size="xs"
                                icon="i-lucide-trash-2"
                                aria-label="Remove this key"
                                @click="router.delete(providerRoutes.destroy.url({ provider: entry.name, index }), { preserveScroll: true })"
                            />
                        </li>
                    </ul>
                </div>
            </div>
        </UCard>

        <UCard>
            <template #header>
                <h2 class="font-medium">
                    Add a key
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
                    label="Name"
                    name="name"
                    description="Defaults to “default” when left blank."
                    :error="errors.name"
                >
                    <UInput
                        name="name"
                        placeholder="default"
                        class="w-full max-w-md"
                    />
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
                    label="Add key"
                />
            </Form>
        </UCard>
    </div>
</template>
