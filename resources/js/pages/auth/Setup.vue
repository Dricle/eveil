<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/Auth/SetupController';
import AuthCard from '@/layouts/AuthCard.vue';
</script>

<template>
    <AuthCard
        title="Create your admin account"
        description="This is a fresh instance. The account you create here administers it."
    >
        <Head title="Setup" />

        <Form
            v-bind="store.form()"
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
        </Form>
    </AuthCard>
</template>
