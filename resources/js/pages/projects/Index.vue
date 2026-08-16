<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { destroy, store, update } from '@/routes/projects'

interface Project {
    id: number
    name: string
    url: string
    analyzed: boolean
}

defineProps<{ projects: Project[] }>()

// One dialog for both, because the two forms are the same two fields.
const editing = ref<Project | null>(null)
const formOpen = ref(false)
const deleting = ref<Project | null>(null)

function openForm (project: Project | null) {
    editing.value = project
    formOpen.value = true
}

function remove () {
    if (deleting.value === null) {
        return
    }

    router.delete(destroy.url(deleting.value.id), {
        onFinish: () => (deleting.value = null)
    })
}
</script>

<template>
    <AppLayout>
        <template #header>
            <h1 class="font-medium">
                Projects
            </h1>

            <UButton
                v-if="projects.length > 0"
                icon="i-lucide-plus"
                label="New project"
                class="ml-auto"
                @click="openForm(null)"
            />
        </template>

        <Head title="Projects" />

        <div class="p-4">
            <div
                v-if="projects.length === 0"
                class="flex flex-col items-center gap-3 py-24 text-center"
            >
                <h2 class="font-medium">
                    No project yet
                </h2>
                <p class="max-w-sm text-sm text-muted">
                    Give the address of the product you want to promote. Its
                    site is read for you — nothing else to fill in.
                </p>
                <UButton
                    icon="i-lucide-plus"
                    label="New project"
                    @click="openForm(null)"
                />
            </div>

            <ul
                v-else
                class="divide-y divide-default rounded-md border border-default"
            >
                <li
                    v-for="project in projects"
                    :key="project.id"
                    class="flex items-center gap-3 p-3"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">
                            {{ project.name }}
                        </p>
                        <p class="truncate text-sm text-muted">
                            {{ project.url }}
                        </p>
                    </div>

                    <UBadge
                        :color="project.analyzed ? 'success' : 'neutral'"
                        variant="subtle"
                        :label="project.analyzed ? 'Analysed' : 'Analysing…'"
                    />

                    <UButton
                        icon="i-lucide-pencil"
                        color="neutral"
                        variant="ghost"
                        aria-label="Edit project"
                        @click="openForm(project)"
                    />
                    <UButton
                        icon="i-lucide-trash-2"
                        color="neutral"
                        variant="ghost"
                        aria-label="Delete project"
                        @click="deleting = project"
                    />
                </li>
            </ul>
        </div>

        <UModal
            v-model:open="formOpen"
            :title="editing ? 'Edit project' : 'New project'"
        >
            <template #body>
                <Form
                    v-slot="{ errors, processing }"
                    v-bind="editing ? update.form(editing.id) : store.form()"
                    class="space-y-4"
                    @success="formOpen = false"
                >
                    <UFormField
                        label="Name"
                        name="name"
                        :error="errors.name"
                    >
                        <UInput
                            name="name"
                            :default-value="editing?.name"
                            required
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        label="Website"
                        name="url"
                        :error="errors.url"
                        help="The site is read once to check it answers, then analysed in the background."
                    >
                        <UInput
                            name="url"
                            :default-value="editing?.url"
                            placeholder="example.com"
                            required
                            class="w-full"
                        />
                    </UFormField>

                    <div class="flex justify-end gap-2">
                        <UButton
                            label="Cancel"
                            color="neutral"
                            variant="ghost"
                            @click="formOpen = false"
                        />
                        <UButton
                            type="submit"
                            :loading="processing"
                            :label="editing ? 'Save' : 'Create'"
                        />
                    </div>
                </Form>
            </template>
        </UModal>

        <UModal
            :open="deleting !== null"
            title="Delete project"
            @update:open="deleting = null"
        >
            <template #body>
                <p class="text-sm text-muted">
                    Deleting <strong>{{ deleting?.name }}</strong> also deletes
                    its leads, companies and campaigns. This cannot be undone.
                </p>
            </template>

            <template #footer>
                <div class="flex w-full justify-end gap-2">
                    <UButton
                        label="Cancel"
                        color="neutral"
                        variant="ghost"
                        @click="deleting = null"
                    />
                    <UButton
                        label="Delete"
                        color="error"
                        @click="remove"
                    />
                </div>
            </template>
        </UModal>
    </AppLayout>
</template>
