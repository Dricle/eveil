<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AuthCard from '@/layouts/AuthCard.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthCard
        title="Reset your password"
        description="We will email you a link to choose a new one."
    >
        <Head title="Forgot password" />

        <UAlert
            v-if="status"
            :description="status"
            color="success"
            variant="soft"
            class="mb-4"
        />

        <Form
            v-bind="email.form()"
            v-slot="{ errors, processing }"
            class="space-y-4"
        >
            <UFormField label="Email" name="email" :error="errors.email">
                <UInput
                    name="email"
                    type="email"
                    autocomplete="username"
                    required
                    autofocus
                    class="w-full"
                />
            </UFormField>

            <UButton
                type="submit"
                :loading="processing"
                block
                label="Email password reset link"
            />

            <Link
                :href="login.url()"
                class="block text-center text-sm text-neutral-500 underline"
                >Back to log in</Link
            >
        </Form>
    </AuthCard>
</template>
