<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import composioRoutes from '@/routes/settings/composio'

defineOptions({ layout: [AppLayout, [SettingsLayout, { title: 'Composio' }]] })

const props = defineProps<{
    configured: boolean
    /** How many connected services go through the current key. */
    connections: number
}>()

const page = usePage()
const toast = useToast()
const slug = computed(() => page.props.currentProject!.slug)

// Replacing or removing the key drops what was connected through the old
// one: say so before it happens, never after.
const removeOpen = ref(false)
const removing = ref(false)

function remove () {
    removing.value = true
    router.delete(composioRoutes.destroy.url({ project: slug.value }), {
        preserveScroll: true,
        onSuccess: () => {
            removeOpen.value = false
            toast.add({ title: 'Composio key removed', color: 'neutral' })
        },
        onFinish: () => removing.value = false
    })
}

const STEPS = [
    'Create a free account on composio.dev.',
    'Create a project for Eveil, or use the default one, so it holds nothing else.',
    'In the developer dashboard (platform.composio.dev), open Settings, then Project Settings, and copy the API key from the API Keys section. It starts with ak_. Not the ck_ key under Sessions & API Key in the Connect space: that one is for MCP clients.',
    'Paste it below.'
]
</script>

<template>
    <Head title="Composio" />

    <div class="space-y-4">
        <div class="space-y-2">
            <p class="text-sm text-muted">
                Services such as Instagram connect through Composio, whose apps are
                already approved by each service: you never have to create one of
                your own. The connections live in your own Composio account, under
                its free plan. Step by step in the
                <a
                    href="https://docs.eveil.cloud/product/integrations"
                    target="_blank"
                    rel="noopener"
                    class="text-primary"
                >documentation</a>.
            </p>

            <UAlert
                v-if="page.props.status"
                color="success"
                variant="subtle"
                icon="i-lucide-check"
                :description="String(page.props.status)"
            />
        </div>

        <UCard>
            <template #header>
                <h2 class="font-medium">
                    Your Composio key
                </h2>
            </template>

            <div class="flex flex-wrap items-center gap-3">
                <p class="min-w-0 flex-1 text-sm text-muted">
                    {{ props.connections }} {{ props.connections === 1 ? 'service' : 'services' }} connected through it.
                </p>

                <UBadge
                    :color="configured ? 'success' : 'neutral'"
                    variant="subtle"
                    :label="configured ? 'Configured' : 'Not configured'"
                />

                <UButton
                    v-if="configured"
                    color="error"
                    variant="ghost"
                    size="xs"
                    icon="i-lucide-trash-2"
                    label="Remove"
                    @click="removeOpen = true"
                />
            </div>
        </UCard>

        <UCard>
            <template #header>
                <h2 class="font-medium">
                    {{ configured ? 'Replace the key' : 'Add your key' }}
                </h2>
            </template>

            <ol class="mb-4 list-decimal space-y-1 ps-5 text-sm text-muted">
                <li
                    v-for="step in STEPS"
                    :key="step"
                >
                    {{ step }}
                </li>
            </ol>

            <UAlert
                v-if="configured && connections > 0"
                class="mb-4"
                color="warning"
                variant="subtle"
                icon="i-lucide-triangle-alert"
                description="A different key cannot reach the connections made with this one: they are disconnected, and you connect those services again afterwards."
            />

            <Form
                v-slot="{ errors, processing }"
                v-bind="composioRoutes.update.form({ project: slug })"
                reset-on-success
                class="space-y-4"
                @success="toast.add({ title: 'Composio key saved', color: 'success' })"
            >
                <UFormField
                    label="Project API key"
                    name="api_key"
                    :error="errors.api_key"
                >
                    <UInput
                        name="api_key"
                        type="password"
                        required
                        class="w-full max-w-md"
                    />
                </UFormField>

                <UButton
                    type="submit"
                    :loading="processing"
                    label="Save"
                />
            </Form>
        </UCard>
    </div>

    <UModal
        v-model:open="removeOpen"
        title="Remove the Composio key?"
        :description="connections > 0 ? `The ${connections} ${connections === 1 ? 'service' : 'services'} connected through it will be disconnected.` : 'Nothing is connected through it yet.'"
    >
        <template #footer>
            <UButton
                color="error"
                label="Remove"
                :loading="removing"
                @click="remove"
            />
        </template>
    </UModal>
</template>
