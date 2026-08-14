<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AccountLayout from '@/layouts/AccountLayout.vue';
import { update } from '@/routes/user-password';
</script>

<template>
    <AccountLayout title="Password">
        <Head title="Password" />

        <UCard>
            <template #header>
                <h2 class="font-medium">Password</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    Changing it does not sign your other sessions out.
                </p>
            </template>

            <Form
                v-bind="update.form()"
                error-bag="updatePassword"
                reset-on-success
                v-slot="{ errors, processing, recentlySuccessful }"
                class="space-y-4"
            >
                <UFormField
                    label="Current password"
                    name="current_password"
                    :error="errors.current_password"
                >
                    <UInput
                        name="current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    label="New password"
                    name="password"
                    :error="errors.password"
                >
                    <UInput
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    label="Confirm new password"
                    name="password_confirmation"
                >
                    <UInput
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full"
                    />
                </UFormField>

                <div class="flex items-center gap-3">
                    <UButton
                        type="submit"
                        :loading="processing"
                        label="Change password"
                    />
                    <span
                        v-if="recentlySuccessful"
                        class="text-sm text-neutral-500"
                        >Changed.</span
                    >
                </div>
            </Form>
        </UCard>
    </AccountLayout>
</template>
