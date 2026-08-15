<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3'
import AuthCard from '@/layouts/AuthCard.vue'
import { store } from '@/routes/login'
import { request } from '@/routes/password'

defineProps<{
    status?: string
}>()

const page = usePage()
</script>

<template>
    <AuthCard title="Log in">
        <Head title="Log in" />

        <UAlert
            v-if="status"
            :description="status"
            color="success"
            variant="soft"
            class="mb-4"
        />

        <Form
            v-slot="{ errors, processing }"
            v-bind="store.form()"
            class="space-y-4"
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
                    required
                    autofocus
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
                    autocomplete="current-password"
                    required
                    class="w-full"
                />
            </UFormField>

            <UCheckbox
                name="remember"
                value="1"
                label="Remember me"
            />

            <UButton
                type="submit"
                :loading="processing"
                block
                label="Log in"
            />

            <Link
                :href="request.url()"
                class="block text-center text-sm text-neutral-500 underline"
            >
                Forgot your password?
            </Link>

            <Link
                v-if="page.props.registerUrl"
                :href="page.props.registerUrl"
                class="block text-center text-sm text-neutral-500 underline"
            >
                No account yet? Register
            </Link>
        </Form>
    </AuthCard>
</template>
