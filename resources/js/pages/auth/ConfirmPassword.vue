<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3'
import AuthCard from '@/layouts/AuthCard.vue'
import { store } from '@/routes/password/confirm'
</script>

<template>
    <AuthCard
        title="Confirm your password"
        description="This is a secure area. Please confirm your password to continue."
    >
        <Head title="Confirm password" />

        <Form
            v-slot="{ errors, processing }"
            v-bind="store.form()"
            class="space-y-4"
        >
            <UFormField
                label="Password"
                name="password"
                :error="errors.password"
            >
                <UInput
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    autofocus
                    class="w-full"
                />
            </UFormField>

            <UButton
                type="submit"
                :loading="processing"
                block
                label="Confirm"
            />
        </Form>
    </AuthCard>
</template>
