<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3'
import AccountLayout from '@/layouts/AccountLayout.vue'
import { destroy } from '@/routes/account'
</script>

<template>
    <AccountLayout title="Delete account">
        <Head title="Delete account" />

        <UCard>
            <template #header>
                <h2 class="font-medium">
                    Delete account
                </h2>
                <p class="mt-1 text-sm text-neutral-500">
                    This cannot be undone. Any organization left without a
                    member goes with it, and so do its projects, leads and
                    campaigns.
                </p>
            </template>

            <Form
                v-slot="{ errors, processing }"
                v-bind="destroy.form()"
                class="space-y-4"
            >
                <UFormField
                    label="Confirm your password"
                    name="password"
                    :error="errors.password"
                >
                    <UInput
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full"
                    />
                </UFormField>

                <UButton
                    type="submit"
                    color="error"
                    :loading="processing"
                    label="Delete my account"
                />
            </Form>
        </UCard>
    </AccountLayout>
</template>
