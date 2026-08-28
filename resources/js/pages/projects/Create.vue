<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3'
import { computed, onMounted, useTemplateRef } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { store } from '@/routes/projects'

const props = defineProps<{
    organizationId: number | null
    // Set once, from the URL a visitor pasted into the marketing homepage's
    // hero form before registering. Prefills the form and, since a name is
    // still needed, drives a same-visit auto-submit so that step never shows
    // as a screen the person has to act on.
    prefillUrl: string | null
}>()

const page = usePage()

const defaultName = computed(() => {
    if (!props.prefillUrl) {
        return ''
    }

    try {
        return new URL(props.prefillUrl).hostname.replace(/^www\./, '')
    } catch {
        return ''
    }
})

const container = useTemplateRef<HTMLDivElement>('container')

onMounted(() => {
    // requestSubmit(), not submit(): the latter bypasses the `submit` event
    // entirely, which is exactly where Inertia's <Form> hooks in to turn
    // this into a visit instead of a real page navigation.
    if (props.prefillUrl) {
        container.value?.querySelector('form')?.requestSubmit()
    }
})
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
                        Give its address. The site is read for you, so there is no
                        lead list to supply.
                    </p>
                </template>

                <div ref="container">
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
                                :default-value="defaultName"
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
                                :default-value="prefillUrl ?? undefined"
                                placeholder="example.com"
                                required
                                class="w-full"
                            />
                        </UFormField>

                        <input
                            v-if="organizationId"
                            type="hidden"
                            name="organization_id"
                            :value="organizationId"
                        >

                        <UButton
                            type="submit"
                            :loading="processing"
                            label="Create project"
                            block
                        />
                    </Form>
                </div>
            </UCard>
        </div>
    </AppLayout>
</template>
