<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AccountLayout from '@/layouts/AccountLayout.vue'
import { update } from '@/routes/user-profile-information'

const page = usePage()

// Bound, never left to `default-value`: Nuxt UI reads that prop once at mount,
// and Vue then patches a form element's value against what the DOM holds, so
// every later render writes the frozen first value back over what was typed.
const name = ref(page.props.auth.user.name)
const email = ref(page.props.auth.user.email)

watch(() => page.props.auth.user, (user) => {
    name.value = user.name
    email.value = user.email
})
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
                        v-model="name"
                        name="name"
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
                        v-model="email"
                        name="email"
                        type="email"
                        autocomplete="username"
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
