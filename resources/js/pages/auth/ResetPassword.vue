<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3'
import AuthCard from '@/layouts/AuthCard.vue'
import { update } from '@/routes/password'

defineProps<{
    email: string
    token: string
}>()
</script>

<template>
    <AuthCard title="Choose a new password">
        <Head title="Reset password" />

        <Form
            v-slot="{ errors, processing }"
            v-bind="update.form()"
            class="space-y-4"
        >
            <input
                type="hidden"
                name="token"
                :value="token"
            >

            <UFormField
                label="Email"
                name="email"
                :error="errors.email"
            >
                <UInput
                    name="email"
                    type="email"
                    autocomplete="username"
                    :default-value="email"
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
                    autofocus
                    class="w-full"
                />
            </UFormField>

            <UFormField
                label="Confirm password"
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

            <UButton
                type="submit"
                :loading="processing"
                block
                label="Reset password"
            />
        </Form>
    </AuthCard>
</template>
