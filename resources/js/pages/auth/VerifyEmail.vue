<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import AuthCard from '@/layouts/AuthCard.vue'
import { logout } from '@/routes'
import { send } from '@/routes/verification'

defineProps<{
    status?: string
}>()
</script>

<template>
    <AuthCard
        title="Verify your email"
        description="Follow the link we just sent you before continuing."
    >
        <Head title="Verify email" />

        <UAlert
            v-if="status === 'verification-link-sent'"
            description="A new verification link has been sent to the email address you provided during registration."
            color="success"
            variant="soft"
            class="mb-4"
        />

        <Form
            v-slot="{ processing, recentlySuccessful }"
            v-bind="send.form()"
            class="space-y-4"
        >
            <UButton
                type="submit"
                :loading="processing"
                block
                label="Resend verification email"
            />
            <p
                v-if="recentlySuccessful"
                class="text-center text-sm text-neutral-500"
            >
                Sent.
            </p>
        </Form>

        <button
            type="button"
            class="mt-4 block w-full text-center text-sm text-neutral-500 underline"
            @click="router.post(logout.url())"
        >
            Log out
        </button>
    </AuthCard>
</template>
