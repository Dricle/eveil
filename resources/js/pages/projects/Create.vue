<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { store } from '@/routes/projects'

const page = usePage()
</script>

<template>
    <AppLayout>
        <Head title="New project" />

        <div class="mx-auto max-w-lg p-4 py-16">
            <UCard>
                <template #header>
                    <h2 class="font-medium">
                        {{
                            page.props.projects.length === 0
                                ? 'Start with the product you want to promote'
                                : 'Add another product'
                        }}
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        Give its address. The site is read for you — there is no
                        lead list to supply.
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
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        label="Website"
                        name="url"
                        :error="errors.url"
                        help="The site is fetched once to check it answers, then analysed in the background."
                    >
                        <UInput
                            name="url"
                            placeholder="example.com"
                            required
                            class="w-full"
                        />
                    </UFormField>

                    <UButton
                        type="submit"
                        :loading="processing"
                        label="Create project"
                        block
                    />
                </Form>
            </UCard>
        </div>
    </AppLayout>
</template>
