<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import projectRoutes from '@/routes/settings/project'
import type { ProjectDetail } from '@/types'

defineProps<{ project: ProjectDetail }>()

const confirmingDelete = ref(false)
</script>

<template>
    <SettingsLayout title="Project">
        <Head title="Project" />

        <div class="max-w-2xl space-y-4">
            <UCard>
                <template #header>
                    <h2 class="font-medium">
                        Name and website
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        Changing the address re-reads the site and rebuilds the
                        knowledge base.
                    </p>
                </template>

                <Form
                    v-slot="{ errors, processing, recentlySuccessful }"
                    v-bind="projectRoutes.update.form()"
                    class="space-y-4"
                >
                    <UFormField
                        label="Name"
                        name="name"
                        :error="errors.name"
                    >
                        <UInput
                            name="name"
                            :default-value="project.name"
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
                            name="url"
                            :default-value="project.url"
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
                            class="text-sm text-muted"
                        >Saved.</span>
                    </div>
                </Form>
            </UCard>

            <UCard>
                <template #header>
                    <h2 class="font-medium">
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
                        variant="soft"
                        label="Delete project"
                        @click="confirmingDelete = true"
                    />
                </template>
            </UCard>
        </div>

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
                        @click="router.delete(projectRoutes.destroy.url())"
                    />
                </div>
            </template>
        </UModal>
    </SettingsLayout>
</template>
