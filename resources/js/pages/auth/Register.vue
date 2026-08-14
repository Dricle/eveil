<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AuthCard from '@/layouts/AuthCard.vue';
import { login } from '@/routes';

// The action comes from the server, not from Wayfinder: with sign-ups closed
// the route does not exist and its generated module is not emitted.
defineProps<{
    action: string;
}>();
</script>

<template>
    <AuthCard title="Create your account">
        <Head title="Register" />

        <Form
            :action="action"
            method="post"
            v-slot="{ errors, processing }"
            class="space-y-4"
        >
            <UFormField label="Your name" name="name" :error="errors.name">
                <UInput name="name" required autofocus class="w-full" />
            </UFormField>

            <UFormField
                label="Organization"
                name="organization"
                :error="errors.organization"
            >
                <UInput name="organization" required class="w-full" />
            </UFormField>

            <UFormField label="Email" name="email" :error="errors.email">
                <UInput
                    name="email"
                    type="email"
                    autocomplete="username"
                    required
                    class="w-full"
                />
            </UFormField>

            <UFormField
                label="Password"
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

            <UFormField label="Confirm password" name="password_confirmation">
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
                label="Create account"
            />

            <Link
                :href="login.url()"
                class="block text-center text-sm text-neutral-500 underline"
                >Already have an account? Log in</Link
            >
        </Form>
    </AuthCard>
</template>
