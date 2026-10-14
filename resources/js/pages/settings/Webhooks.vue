<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import webhookRoutes from '@/routes/settings/webhooks'

defineOptions({ layout: [AppLayout, [SettingsLayout, { title: 'Webhooks' }]] })

const props = defineProps<{
    webhooks: { id: number, url: string, events: string[], last_delivered_at: string | null, last_status: number | null, last_error: string | null, disabled: boolean }[]
    events: string[]
    secret: string | null
}>()

const page = usePage()
const toast = useToast()

// Same words as `docs/product/webhooks.md`.
const EVENT_LABELS: Record<string, string> = {
    'reply.received': 'A reply was read (interested, not now, unsubscribe...)',
    'status.changed': 'A contact or company changed status (won, lost, client...)'
}

const form = useForm({ url: '', events: [...props.events] })
const testing = ref<number | null>(null)

function create () {
    form.post(webhookRoutes.store.url({ project: page.props.currentProject!.slug }), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('url')
            toast.add({ title: 'Webhook added', color: 'success' })
        }
    })
}

function test (id: number) {
    testing.value = id
    router.post(webhookRoutes.test.url({ project: page.props.currentProject!.slug, webhook: id }), {}, {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'The endpoint answered', color: 'success' }),
        onError: errors => toast.add({ title: 'The endpoint did not answer', description: errors.test, color: 'error' }),
        onFinish: () => { testing.value = null }
    })
}

function remove (id: number) {
    router.delete(webhookRoutes.destroy.url({ project: page.props.currentProject!.slug, webhook: id }), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Webhook deleted', color: 'success' })
    })
}

async function copy (secret: string) {
    try {
        await navigator.clipboard.writeText(secret)
        toast.add({ title: 'Copied', color: 'success' })
    } catch {
        toast.add({ title: 'Could not copy - select and copy manually', color: 'error' })
    }
}
</script>

<template>
    <Head title="Webhooks" />

    <div class="space-y-6">
        <p class="text-sm text-muted">
            Eveil POSTs to these addresses when something happens in this
            project, so a CRM, or an n8n, Zapier or Make flow in front of it,
            hears about it straight away. Each request is signed with the
            endpoint's secret in <code>X-Eveil-Signature</code>.
            <a
                href="https://docs.eveil.cloud/product/webhooks"
                target="_blank"
                class="text-primary underline"
            >Webhook documentation and n8n recipes</a>
        </p>

        <UAlert
            v-if="secret"
            color="warning"
            variant="subtle"
            icon="i-lucide-key-round"
            title="Copy this signing secret now. It will not be shown again."
        >
            <template #description>
                <div class="mt-2 flex items-center gap-2">
                    <code class="min-w-0 flex-1 break-all">{{ secret }}</code>
                    <UButton
                        icon="i-lucide-copy"
                        size="sm"
                        variant="outline"
                        label="Copy"
                        @click="copy(secret)"
                    />
                </div>
            </template>
        </UAlert>

        <form
            class="space-y-3"
            @submit.prevent="create"
        >
            <UFormField
                label="Endpoint URL"
                :error="form.errors.url"
            >
                <UInput
                    v-model="form.url"
                    placeholder="https://n8n.example.com/webhook/eveil"
                    class="w-full"
                />
            </UFormField>
            <UFormField
                label="Send"
                :error="form.errors.events"
            >
                <UCheckboxGroup
                    v-model="form.events"
                    :items="events.map(event => ({ value: event, label: EVENT_LABELS[event] ?? event }))"
                />
            </UFormField>
            <UButton
                type="submit"
                label="Add webhook"
                :loading="form.processing"
            />
        </form>

        <div class="space-y-2">
            <div
                v-for="webhook in webhooks"
                :key="webhook.id"
                class="flex items-center justify-between gap-4 rounded-lg p-4 ring ring-default"
            >
                <div class="min-w-0">
                    <div class="truncate font-medium">
                        {{ webhook.url }}
                    </div>
                    <div class="text-sm text-muted">
                        {{ webhook.events.join(', ') }}
                    </div>
                    <div
                        v-if="webhook.disabled"
                        class="text-sm text-error"
                    >
                        Turned off after a day of failures: {{ webhook.last_error }}. Fix the endpoint, then send a test to turn it back on.
                    </div>
                    <div
                        v-else-if="webhook.last_delivered_at"
                        class="text-sm"
                        :class="webhook.last_error ? 'text-error' : 'text-muted'"
                    >
                        {{ webhook.last_error ? `Failing: ${webhook.last_error}` : 'Delivered' }},
                        {{ new Date(webhook.last_delivered_at).toLocaleString() }}
                    </div>
                </div>
                <div class="flex shrink-0 gap-2">
                    <UButton
                        variant="outline"
                        label="Send test"
                        :loading="testing === webhook.id"
                        @click="test(webhook.id)"
                    />
                    <UButton
                        color="error"
                        variant="ghost"
                        label="Delete"
                        @click="remove(webhook.id)"
                    />
                </div>
            </div>

            <p
                v-if="webhooks.length === 0"
                class="text-sm text-muted"
            >
                No webhooks yet.
            </p>
        </div>
    </div>
</template>
