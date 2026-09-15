<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import LinkedinHeader from '@/components/LinkedinHeader.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import linkedinRoutes from '@/routes/linkedin/account'
import type { LinkedinAccount, Project } from '@/types'

defineOptions({ layout: AppLayout })

// Inline rather than a type alias imported through the barrel: an alias there
// silently declares no props at all.
const props = defineProps<{
    accounts: LinkedinAccount[]
    projects: Project[]
    currentProjectFrequency: 'off' | 'daily' | 'weekly' | 'biweekly' | 'monthly'
}>()

const page = usePage()

const editing = ref<LinkedinAccount | null>(null)
const editingOpen = ref(false)
const editingProjects = ref<number[]>([])

function open (account: LinkedinAccount) {
    editing.value = account
    editingProjects.value = [...account.projects]
    editingOpen.value = true
}

// A local draft synced from the prop, not `default-value`: Nuxt UI's select
// reads that once and every re-render (this page's own redirect included)
// would silently stomp whatever the user just picked.
const frequency = ref(props.currentProjectFrequency)
watch(() => props.currentProjectFrequency, value => frequency.value = value, { immediate: true })

const FREQUENCIES = [
    { label: 'Off', value: 'off' },
    { label: 'Daily', value: 'daily' },
    { label: 'Weekly', value: 'weekly' },
    { label: 'Every two weeks', value: 'biweekly' },
    { label: 'Monthly', value: 'monthly' }
]

const STATUS = {
    active: { color: 'success' as const, label: 'Active' },
    expired: { color: 'warning' as const, label: 'Expired' },
    error: { color: 'error' as const, label: 'Not working' }
}
</script>

<template>
    <Head title="LinkedIn account" />

    <div class="space-y-6 p-6">
        <LinkedinHeader tab="account" />

        <div class="space-y-2">
            <p class="text-sm text-muted">
                Personal-profile posting only, via LinkedIn's official API. No
                automation of connection requests or messages: publishing a post
                is the only thing this connects.
            </p>

            <UAlert
                v-if="page.props.status"
                color="success"
                variant="subtle"
                icon="i-lucide-check"
                :description="String(page.props.status)"
            />
        </div>

        <div class="space-y-3">
            <h3 class="text-sm font-medium text-muted">
                Connected accounts
            </h3>

            <div
                v-for="account in accounts"
                :key="account.id"
                class="space-y-2 rounded-lg p-4 ring ring-default"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            {{ account.display_name }}
                        </p>
                    </div>

                    <UBadge
                        :color="STATUS[account.status].color"
                        variant="subtle"
                        :label="STATUS[account.status].label"
                    />

                    <UButton
                        icon="i-lucide-pencil"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        label="Projects"
                        @click="open(account)"
                    />

                    <UButton
                        icon="i-lucide-trash-2"
                        color="error"
                        variant="ghost"
                        size="xs"
                        @click="router.delete(linkedinRoutes.destroy.url(account.id))"
                    />
                </div>

                <p
                    v-if="account.last_error"
                    class="text-sm text-error"
                >
                    {{ account.last_error }}
                </p>

                <p
                    v-if="account.projects.length === 0"
                    class="text-sm text-dimmed"
                >
                    No project may draft/post through it yet.
                </p>
            </div>

            <p
                v-if="!accounts.length"
                class="rounded-lg p-6 text-sm text-muted ring ring-default"
            >
                No LinkedIn account connected yet.
            </p>

            <UButton
                icon="i-lucide-plug"
                label="Connect a LinkedIn account"
                :href="linkedinRoutes.connect.url()"
            />
        </div>

        <div class="space-y-3">
            <h3 class="text-sm font-medium text-muted">
                Posting cadence for this project
            </h3>

            <Form
                v-slot="{ processing }"
                v-bind="linkedinRoutes.cadence.form()"
                class="flex items-end gap-3"
            >
                <UFormField label="New post">
                    <USelect
                        v-model="frequency"
                        name="linkedin_post_frequency"
                        :items="FREQUENCIES"
                        class="w-56"
                    />
                </UFormField>

                <UButton
                    type="submit"
                    label="Save"
                    :loading="processing"
                />
            </Form>
        </div>
    </div>

    <UModal
        v-model:open="editingOpen"
        title="Projects allowed to draft/post through this account"
        :ui="{ content: 'max-w-lg' }"
    >
        <template #body>
            <Form
                v-if="editing"
                v-slot="{ processing }"
                v-bind="linkedinRoutes.update.form(editing.id)"
                class="space-y-4"
                @success="editingOpen = false"
            >
                <UCheckboxGroup
                    v-model="editingProjects"
                    :items="projects"
                    value-key="id"
                    label-key="name"
                />

                <input
                    v-for="id in editingProjects"
                    :key="id"
                    type="hidden"
                    name="projects[]"
                    :value="id"
                >

                <UButton
                    type="submit"
                    :loading="processing"
                    label="Save"
                />
            </Form>
        </template>
    </UModal>
</template>
