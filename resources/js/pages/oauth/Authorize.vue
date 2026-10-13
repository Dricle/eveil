<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import AuthCard from '@/layouts/AuthCard.vue'
import { approve as approveRoute, deny as denyRoute } from '@/routes/passport/authorizations'

/*
 * Plain forms, not Inertia ones: both answers redirect to the assistant's own
 * callback URL, on another site, which an Inertia visit cannot follow.
 */
defineProps<{
    client: string
    authToken: string
    csrfToken: string
}>()

const submitting = ref(false)
</script>

<template>
    <AuthCard
        title="Connect an assistant"
        :description="`${client} wants to read and act on your projects.`"
    >
        <Head title="Connect an assistant" />

        <div class="space-y-4">
            <p class="text-sm text-muted">
                It acts as you, in every project you can open: it can read companies, contacts and campaigns,
                add contacts, start searches, write sequences, posts and articles, and do anything else Evie can.
                Disconnect it any time from your account settings.
            </p>

            <form
                :action="approveRoute.url()"
                method="post"
                @submit="submitting = true"
            >
                <input
                    type="hidden"
                    name="_token"
                    :value="csrfToken"
                >
                <input
                    type="hidden"
                    name="auth_token"
                    :value="authToken"
                >

                <UButton
                    type="submit"
                    block
                    label="Connect"
                    :loading="submitting"
                />
            </form>

            <form
                :action="denyRoute.url()"
                method="post"
            >
                <input
                    type="hidden"
                    name="_method"
                    value="DELETE"
                >
                <input
                    type="hidden"
                    name="_token"
                    :value="csrfToken"
                >
                <input
                    type="hidden"
                    name="auth_token"
                    :value="authToken"
                >

                <UButton
                    type="submit"
                    block
                    color="neutral"
                    variant="ghost"
                    label="Cancel"
                />
            </form>
        </div>
    </AuthCard>
</template>
