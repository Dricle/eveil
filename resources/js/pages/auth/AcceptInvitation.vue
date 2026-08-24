<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import { logout } from '@/routes'
import AuthCard from '@/layouts/AuthCard.vue'

// The signed URL itself, query string and signature included: there is no
// invite row to look up by id, so the POST reuses this exact address rather
// than a Wayfinder route.
const props = defineProps<{
    acceptUrl: string
    organizationName: string
    email: string
    authenticatedAs: string | null
}>()
</script>

<template>
    <AuthCard
        title="You're invited"
        :description="`Join ${props.organizationName} on Eveil.`"
    >
        <Head title="Accept invitation" />

        <!-- Signed in under a different address: accepting as them would
             join the wrong account to this organization, so logging out
             first is the only safe path. -->
        <div
            v-if="authenticatedAs && authenticatedAs !== email"
            class="space-y-3"
        >
            <p class="text-sm text-muted">
                This invitation is for <strong>{{ email }}</strong>, but you're
                signed in as {{ authenticatedAs }}.
            </p>
            <UButton
                :label="`Log out of ${authenticatedAs}`"
                block
                variant="soft"
                @click="router.post(logout.url())"
            />
        </div>

        <Form
            v-else-if="authenticatedAs"
            v-slot="{ processing }"
            :action="acceptUrl"
            method="post"
        >
            <UButton
                type="submit"
                :loading="processing"
                block
                :label="`Join ${organizationName}`"
            />
        </Form>

        <Form
            v-else
            v-slot="{ errors, processing }"
            :action="acceptUrl"
            method="post"
            class="space-y-4"
        >
            <p class="text-sm text-muted">
                {{ email }}
            </p>

            <UFormField
                label="Your name"
                name="name"
                :error="errors.name"
            >
                <UInput
                    name="name"
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
                    autocomplete="new-password"
                    required
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
                label="Create account and join"
            />
        </Form>
    </AuthCard>
</template>
