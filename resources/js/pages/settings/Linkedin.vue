<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import linkedinRoutes from '@/routes/settings/linkedin'
import type { LinkedinAccount, Project } from '@/types'

defineOptions({ layout: [AppLayout, [SettingsLayout, { title: 'LinkedIn' }]] })

// Inline rather than a type alias imported through the barrel: an alias there
// silently declares no props at all.
defineProps<{
    accounts: LinkedinAccount[]
    projects: Project[]
    statsConfigured: boolean
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

const STATUS = {
    active: { color: 'success' as const, label: 'Active' },
    expired: { color: 'warning' as const, label: 'Expired' },
    error: { color: 'error' as const, label: 'Not working' }
}
</script>

<template>
    <Head title="LinkedIn" />

    <div class="space-y-6">
        <div class="space-y-2">
            <p class="text-sm text-muted">
                Personal-profile posting only, via LinkedIn's official API. No
                automation of connection requests or messages: publishing a post
                is the only thing this connects. The posting cadence is set from
                the LinkedIn posts queue, not here.
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
                        @click="router.delete(linkedinRoutes.destroy.url({ project: page.props.currentProject!.slug, linkedinAccount: account.id }))"
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

                <UBadge
                    v-if="account.has_stats_access"
                    color="success"
                    variant="subtle"
                    label="Performance polling enabled"
                />
                <UButton
                    v-else-if="statsConfigured"
                    icon="i-lucide-line-chart"
                    color="neutral"
                    variant="outline"
                    size="xs"
                    label="Connect performance polling"
                    :href="linkedinRoutes.stats.connect.url({ project: page.props.currentProject!.slug, linkedinAccount: account.id })"
                    external
                />
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
                :href="linkedinRoutes.connect.url({ project: page.props.currentProject!.slug })"
                external
            />
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
                v-bind="linkedinRoutes.update.form({ project: page.props.currentProject!.slug, linkedinAccount: editing.id })"
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
