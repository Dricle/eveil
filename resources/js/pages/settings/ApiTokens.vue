<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import apiTokenRoutes from '@/routes/settings/api-tokens'

defineOptions({ layout: [AppLayout, [SettingsLayout, { title: 'API' }]] })

defineProps<{
    tokens: { id: number, name: string, last_used_at: string | null, created_at: string }[]
    plainTextToken: string | null
    apiUrl: string
}>()

const page = usePage()
const toast = useToast()

async function copy (token: string) {
    try {
        await navigator.clipboard.writeText(token)
        toast.add({ title: 'Copied', color: 'success' })
    } catch {
        toast.add({ title: 'Could not copy - select and copy manually', color: 'error' })
    }
}

function revoke (id: number) {
    router.delete(apiTokenRoutes.destroy.url({ project: page.props.currentProject!.slug, token: id }), { preserveScroll: true })
}
</script>

<template>
    <Head title="API" />

    <div class="space-y-6">
        <p class="text-sm text-muted">
            A token reaches this project and nothing else. Send it as
            <code>Authorization: Bearer &lt;token&gt;</code> to
            <code>{{ apiUrl }}</code>.
            <a
                href="https://docs.eveil.cloud/product/api"
                target="_blank"
                class="text-primary underline"
            >API documentation</a>
        </p>

        <UAlert
            v-if="plainTextToken"
            color="warning"
            variant="subtle"
            icon="i-lucide-key-round"
            title="Copy this token now. It will not be shown again."
        >
            <template #description>
                <div class="mt-2 flex items-center gap-2">
                    <code class="min-w-0 flex-1 break-all">{{ plainTextToken }}</code>
                    <UButton
                        icon="i-lucide-copy"
                        size="sm"
                        variant="outline"
                        label="Copy"
                        @click="copy(plainTextToken)"
                    />
                </div>
            </template>
        </UAlert>

        <Form
            v-slot="{ errors, processing }"
            v-bind="apiTokenRoutes.store.form({ project: page.props.currentProject!.slug })"
            reset-on-success
            class="flex items-start gap-2"
        >
            <UFormField
                :error="errors.name"
                class="flex-1"
            >
                <UInput
                    name="name"
                    placeholder="What uses it, e.g. CRM sync"
                    class="w-full"
                />
            </UFormField>
            <UButton
                type="submit"
                label="Create token"
                :loading="processing"
            />
        </Form>

        <div class="space-y-2">
            <div
                v-for="token in tokens"
                :key="token.id"
                class="flex items-center justify-between gap-4 rounded-lg p-4 ring ring-default"
            >
                <div class="min-w-0">
                    <div class="font-medium">
                        {{ token.name }}
                    </div>
                    <div class="text-sm text-muted">
                        Created {{ new Date(token.created_at).toLocaleDateString() }},
                        {{ token.last_used_at ? `last used ${new Date(token.last_used_at).toLocaleString()}` : 'never used' }}
                    </div>
                </div>
                <UButton
                    color="error"
                    variant="ghost"
                    label="Revoke"
                    @click="revoke(token.id)"
                />
            </div>

            <p
                v-if="tokens.length === 0"
                class="text-sm text-muted"
            >
                No tokens yet.
            </p>
        </div>
    </div>
</template>
