<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3'
import AccountLayout from '@/layouts/AccountLayout.vue'
import { update } from '@/routes/user-profile-information'

const page = usePage()
</script>

<template>
    <AccountLayout title="Profile">
        <Head title="Profile" />

        <UCard>
            <template #header>
                <h2 class="font-medium">
                    Profile
                </h2>
                <p class="mt-1 text-sm text-neutral-500">
                    Your name and the address you sign in with.
                </p>
            </template>

            <Form
                v-slot="{ errors, processing, recentlySuccessful }"
                v-bind="update.form()"
                error-bag="updateProfileInformation"
                class="space-y-4"
            >
                <UFormField
                    label="Name"
                    name="name"
                    :error="errors.name"
                >
                    <UInput
                        name="name"
                        :default-value="page.props.auth.user.name"
                        required
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    label="Email"
                    name="email"
                    :error="errors.email"
                >
                    <UInput
                        name="email"
                        type="email"
                        autocomplete="username"
                        :default-value="page.props.auth.user.email"
                        required
                        class="w-full"
                    />
                </UFormField>

                <div class="flex items-center gap-3">
                    <UButton
                        type="submit"
                        :loading="processing"
                        label="Save"
                    />
                    <span
                        v-if="recentlySuccessful"
                        class="text-sm text-neutral-500"
                    >Saved.</span>
                </div>
            </Form>
        </UCard>
    </AccountLayout>
</template>
