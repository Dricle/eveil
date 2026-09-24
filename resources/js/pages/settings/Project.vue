<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import companyRoutes from '@/routes/settings/companies'
import contactRoutes from '@/routes/settings/contacts'
import projectRoutes from '@/routes/settings/project'
import type { ProjectDetail } from '@/types'

defineOptions({ layout: [AppLayout, [SettingsLayout, { title: 'Project' }]] })

const props = defineProps<{ project: ProjectDetail }>()

const confirmingCompanyDelete = ref(false)
const confirmingLeadDelete = ref(false)
const confirmingDelete = ref(false)

// Every field is bound, never left to `default-value`: Nuxt UI reads that prop
// once at mount, and Vue then patches a form element's value against what the
// DOM holds, so every later render writes the frozen first value back over what
// was typed. Saving re-renders this page, which is exactly when it bites.
const name = ref(props.project.name)
const url = ref(props.project.url)
const dailyLeadLimit = ref(props.project.daily_lead_limit)
const leadLimit = ref(props.project.lead_limit)

watch(() => props.project, (project) => {
    name.value = project.name
    url.value = project.url
    dailyLeadLimit.value = project.daily_lead_limit
    leadLimit.value = project.lead_limit
})
</script>

<template>
    <Head title="Project" />

    <div class="space-y-4">
        <UCard>
            <template #header>
                <h2 class="font-medium">
                    Project
                </h2>
                <p class="mt-1 text-sm text-muted">
                    Changing the address re-reads the site and rebuilds the
                    knowledge base.
                </p>
            </template>

            <Form
                v-slot="{ errors, processing, recentlySuccessful }"
                v-bind="projectRoutes.update.form({ project: project.slug })"
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
                    label="Website"
                    name="url"
                    :error="errors.url"
                >
                    <UInput
                        v-model="url"
                        name="url"
                        required
                        class="w-full"
                    />
                </UFormField>

                <!-- Continuous discovery's throttle: how far it may go
                     before it stops on its own, for today and forever. -->
                <UFormField
                    label="New leads per day"
                    name="daily_lead_limit"
                    :error="errors.daily_lead_limit"
                    help="Discovery and contact-finding pause for the rest of the day once this many new leads have been found today. Leave empty for no daily cap. Counts every lead on the project, however it was found."
                >
                    <UInput
                        v-model="dailyLeadLimit"
                        type="number"
                        name="daily_lead_limit"
                        min="1"
                        placeholder="No daily cap"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    label="New leads, ever"
                    name="lead_limit"
                    :error="errors.lead_limit"
                    help="Discovery and contact-finding stop for good once the project has this many leads in total. Leave empty for no lifetime cap."
                >
                    <UInput
                        v-model="leadLimit"
                        type="number"
                        name="lead_limit"
                        min="1"
                        placeholder="No lifetime cap"
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
                        class="text-sm text-muted"
                    >Saved.</span>
                </div>
            </Form>
        </UCard>

        <UCard class="border-error/60 bg-error/5">
            <template #header>
                <h2 class="flex items-center gap-1.5 font-medium text-error">
                    <UIcon
                        name="i-lucide-triangle-alert"
                        class="size-4"
                    />
                    Delete all companies
                </h2>
            </template>

            <p class="text-sm text-muted">
                Deletes every company found for this project, along with
                their notes and fit scores. Leads are untouched - a discovery
                run finds them again from nothing. This cannot be undone.
            </p>

            <template #footer>
                <UButton
                    color="error"
                    variant="solid"
                    icon="i-lucide-triangle-alert"
                    label="Delete all companies"
                    @click="confirmingCompanyDelete = true"
                />
            </template>
        </UCard>

        <UCard class="border-error/60 bg-error/5">
            <template #header>
                <h2 class="flex items-center gap-1.5 font-medium text-error">
                    <UIcon
                        name="i-lucide-triangle-alert"
                        class="size-4"
                    />
                    Delete all leads
                </h2>
            </template>

            <p class="text-sm text-muted">
                Deletes every lead found for this project, along with their
                notes and message history. Companies are untouched. This
                cannot be undone.
            </p>

            <template #footer>
                <UButton
                    color="error"
                    variant="solid"
                    icon="i-lucide-triangle-alert"
                    label="Delete all leads"
                    @click="confirmingLeadDelete = true"
                />
            </template>
        </UCard>

        <UCard class="border-error/60 bg-error/5">
            <template #header>
                <h2 class="flex items-center gap-1.5 font-medium text-error">
                    <UIcon
                        name="i-lucide-triangle-alert"
                        class="size-4"
                    />
                    Delete this project
                </h2>
            </template>

            <p class="text-sm text-muted">
                Deleting <strong>{{ project.name }}</strong> also
                deletes its leads, companies and campaigns. This cannot be
                undone.
            </p>

            <template #footer>
                <UButton
                    color="error"
                    variant="solid"
                    icon="i-lucide-triangle-alert"
                    label="Delete project"
                    @click="confirmingDelete = true"
                />
            </template>
        </UCard>
    </div>

    <UModal
        v-model:open="confirmingCompanyDelete"
        title="Delete all companies"
    >
        <template #body>
            <p class="text-sm text-muted">
                Every company found for <strong>{{ project.name }}</strong>
                will be deleted. Leads are untouched.
            </p>
        </template>

        <template #footer>
            <div class="flex w-full justify-end gap-2">
                <UButton
                    label="Cancel"
                    color="neutral"
                    variant="ghost"
                    @click="confirmingCompanyDelete = false"
                />
                <UButton
                    label="Delete all companies"
                    color="error"
                    @click="router.delete(companyRoutes.destroyAll.url({ project: project.slug }))"
                />
            </div>
        </template>
    </UModal>

    <UModal
        v-model:open="confirmingLeadDelete"
        title="Delete all leads"
    >
        <template #body>
            <p class="text-sm text-muted">
                Every lead found for <strong>{{ project.name }}</strong>
                will be deleted. Companies are untouched.
            </p>
        </template>

        <template #footer>
            <div class="flex w-full justify-end gap-2">
                <UButton
                    label="Cancel"
                    color="neutral"
                    variant="ghost"
                    @click="confirmingLeadDelete = false"
                />
                <UButton
                    label="Delete all leads"
                    color="error"
                    @click="router.delete(contactRoutes.destroyAll.url({ project: project.slug }))"
                />
            </div>
        </template>
    </UModal>

    <UModal
        v-model:open="confirmingDelete"
        title="Delete project"
    >
        <template #body>
            <p class="text-sm text-muted">
                <strong>{{ project.name }}</strong> and everything
                found for it will be deleted.
            </p>
        </template>

        <template #footer>
            <div class="flex w-full justify-end gap-2">
                <UButton
                    label="Cancel"
                    color="neutral"
                    variant="ghost"
                    @click="confirmingDelete = false"
                />
                <UButton
                    label="Delete"
                    color="error"
                    @click="router.delete(projectRoutes.destroy.url({ project: project.slug }))"
                />
            </div>
        </template>
    </UModal>
</template>
