<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import AppSettingsLayout from '@/layouts/AppSettingsLayout.vue'
import linkedinRoutes from '@/routes/app-settings/linkedin'

defineOptions({ layout: [AppLayout, [AppSettingsLayout, { title: 'LinkedIn' }]] })

defineProps<{
    clientId: string | null
    configured: boolean
}>()
</script>

<template>
    <Head title="LinkedIn" />

    <div class="space-y-4">
        <UCard>
            <template #header>
                <h2 class="font-medium">
                    LinkedIn app
                </h2>
                <p class="mt-1 text-sm text-muted">
                    This instance's own LinkedIn Developer App: a client id and
                    secret from a "Sign In with LinkedIn using OpenID Connect"
                    plus "Share on LinkedIn" app, one per instance. The secret
                    is encrypted and never sent back to this page.
                </p>
            </template>

            <div class="flex flex-wrap items-center gap-3 rounded-lg p-3 ring ring-default">
                <div class="min-w-0 flex-1">
                    <p class="font-medium">
                        {{ clientId ?? 'Not configured' }}
                    </p>
                </div>

                <UBadge
                    :color="configured ? 'success' : 'error'"
                    variant="subtle"
                    :label="configured ? 'Configured' : 'No app configured'"
                />

                <UButton
                    v-if="configured"
                    color="error"
                    variant="ghost"
                    size="xs"
                    icon="i-lucide-trash-2"
                    aria-label="Remove the stored credentials"
                    @click="router.delete(linkedinRoutes.destroy.url(), { preserveScroll: true })"
                />
            </div>
        </UCard>

        <UCard>
            <template #header>
                <h2 class="font-medium">
                    Save the app credentials
                </h2>
            </template>

            <Form
                v-slot="{ errors, processing }"
                v-bind="linkedinRoutes.update.form()"
                reset-on-success
                class="space-y-4"
            >
                <UFormField
                    label="Client ID"
                    name="client_id"
                    :error="errors.client_id"
                >
                    <UInput
                        name="client_id"
                        required
                        class="w-full max-w-md"
                    />
                </UFormField>

                <UFormField
                    label="Client secret"
                    name="client_secret"
                    :error="errors.client_secret"
                >
                    <UInput
                        name="client_secret"
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
</template>
