<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { store } from '@/routes/organizations'
</script>

<template>
    <AppLayout>
        <Head title="New organization" />

        <div class="mx-auto max-w-lg p-4 py-16">
            <UCard>
                <template #header>
                    <h2 class="font-medium">
                        New organization
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        A separate team, a separate wallet, its own projects.
                        You stay the owner of both.
                    </p>
                </template>

                <Form
                    v-slot="{ errors, processing }"
                    v-bind="store.form()"
                    class="space-y-4"
                >
                    <UFormField
                        label="Name"
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

                    <UButton
                        type="submit"
                        :loading="processing"
                        label="Create organization"
                        block
                    />
                </Form>
            </UCard>
        </div>
    </AppLayout>
</template>
