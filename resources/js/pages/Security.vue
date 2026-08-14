<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import {
    confirm,
    disable,
    enable,
    regenerateRecoveryCodes,
} from '@/routes/two-factor';

defineProps<{
    twoFactorEnabled: boolean;
    twoFactorConfirmed: boolean;
    qrCode: string | null;
    recoveryCodes: string[];
}>();
</script>

<template>
    <div class="mx-auto max-w-2xl space-y-6 p-6">
        <Head title="Security" />

        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold">Security</h1>
            <Link
                :href="dashboard.url()"
                class="text-sm text-neutral-500 underline"
                >Back to dashboard</Link
            >
        </div>

        <UCard>
            <template #header>
                <h2 class="font-medium">Two-factor authentication</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    A code from your authenticator app is required on top of
                    your password.
                </p>
            </template>

            <div v-if="!twoFactorEnabled">
                <Form v-bind="enable.form()" v-slot="{ processing }">
                    <UButton
                        type="submit"
                        :loading="processing"
                        label="Enable two-factor authentication"
                    />
                </Form>
            </div>

            <div v-else class="space-y-4">
                <div v-if="!twoFactorConfirmed" class="space-y-4">
                    <p class="text-sm">
                        Scan this QR code with your authenticator app, then
                        enter the code it shows.
                    </p>

                    <!-- eslint-disable-next-line vue/no-v-html -- the SVG is generated server-side by Fortify -->
                    <div v-html="qrCode" />

                    <Form
                        v-bind="confirm.form()"
                        v-slot="{ errors, processing }"
                        class="flex items-end gap-2"
                    >
                        <UFormField
                            label="Code"
                            name="code"
                            :error="errors.code"
                        >
                            <UInput
                                name="code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                required
                            />
                        </UFormField>

                        <UButton
                            type="submit"
                            :loading="processing"
                            label="Confirm"
                        />
                    </Form>
                </div>

                <div v-if="recoveryCodes.length" class="space-y-2">
                    <h3 class="text-sm font-medium">Recovery codes</h3>
                    <p class="text-sm text-neutral-500">
                        Store these somewhere safe. Each one logs you in once if
                        you lose your authenticator.
                    </p>
                    <ul
                        class="rounded-md bg-neutral-100 p-3 font-mono text-sm dark:bg-neutral-800"
                    >
                        <li v-for="code in recoveryCodes" :key="code">
                            {{ code }}
                        </li>
                    </ul>

                    <Form
                        v-bind="regenerateRecoveryCodes.form()"
                        v-slot="{ processing }"
                    >
                        <UButton
                            type="submit"
                            variant="ghost"
                            :loading="processing"
                            label="Regenerate recovery codes"
                        />
                    </Form>
                </div>

                <Form v-bind="disable.form()" v-slot="{ processing }">
                    <UButton
                        type="submit"
                        color="error"
                        variant="ghost"
                        :loading="processing"
                        label="Disable two-factor authentication"
                    />
                </Form>
            </div>
        </UCard>
    </div>
</template>
