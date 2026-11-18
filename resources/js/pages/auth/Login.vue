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
    <AuthCard
        title="Welcome back"
        description="Log in to pick up where Eveil left off."
    >
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
            class="space-y-5"
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
                    placeholder="you@company.com"
                    icon="i-lucide-mail"
                    size="lg"
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
                <template #hint>
                    <Link
                        :href="request.url()"
                        class="text-xs text-muted transition-colors hover:text-highlighted"
                    >
                        Forgot it?
                    </Link>
                </template>
                <UInput
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    icon="i-lucide-lock"
                    size="lg"
                    required
                    class="w-full"
                />
            </UFormField>

            <UCheckbox
                name="remember"
                value="1"
                label="Keep me logged in"
            />

            <UButton
                type="submit"
                :loading="processing"
                size="lg"
                block
                trailing-icon="i-lucide-arrow-right"
                :ui="{ trailingIcon: 'ms-0' }"
                label="Log in"
            />
        </Form>

        <p
            v-if="page.props.registerUrl"
            class="mt-8 text-sm text-muted"
        >
            No account yet?
            <Link
                :href="page.props.registerUrl"
                class="font-medium text-highlighted underline underline-offset-2"
            >
                Create one
            </Link>
        </p>
    </AuthCard>
</template>
