<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import socialRoutes from '@/routes/settings/social'
import type { Project, SocialAccount } from '@/types'

defineOptions({ layout: [AppLayout, [SettingsLayout, { title: 'Bluesky' }]] })

// Inline rather than a type alias imported through the barrel: an alias there
// silently declares no props at all.
defineProps<{
    accounts: SocialAccount[]
    projects: Project[]
}>()

const page = usePage()
const toast = useToast()
const slug = computed(() => page.props.currentProject!.slug)

const handle = ref('')
const appPassword = ref('')

const editing = ref<SocialAccount | null>(null)
const editingOpen = ref(false)
const editingProjects = ref<number[]>([])

function open (account: SocialAccount) {
    editing.value = account
    editingProjects.value = [...(account.projects ?? [])]
    editingOpen.value = true
}

function destroy (account: SocialAccount) {
    router.delete(socialRoutes.destroy.url({ project: slug.value, socialAccount: account.id }), {
        onSuccess: () => toast.add({ title: 'Account disconnected', color: 'neutral' })
    })
}

const STATUS = {
    active: { color: 'success' as const, label: 'Active' },
    error: { color: 'error' as const, label: 'Reconnect needed' }
}
</script>

<template>
    <Head title="Bluesky" />

    <div class="space-y-6">
        <div class="space-y-2">
            <p class="text-sm text-muted">
                Bluesky posts are published through Bluesky's own API, with an app
                password: create one in Bluesky under Settings, Privacy and security,
                App passwords. It can be revoked there at any time and cannot change
                your account. X needs no account here: you post those yourself.
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
                        <p class="text-sm text-muted">
                            @{{ account.handle }}
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
                        @click="destroy(account)"
                    />
                </div>

                <p
                    v-if="account.last_error"
                    class="text-sm text-error"
                >
                    {{ account.last_error }} Connect it again below with a new app password.
                </p>

                <p
                    v-if="!account.projects?.length"
                    class="text-sm text-dimmed"
                >
                    No project may post through it yet.
                </p>
            </div>

            <p
                v-if="!accounts.length"
                class="rounded-lg p-6 text-sm text-muted ring ring-default"
            >
                No Bluesky account connected yet.
            </p>
        </div>

        <UCard>
            <template #header>
                <h3 class="font-medium">
                    Connect a Bluesky account
                </h3>
            </template>

            <Form
                v-slot="{ errors, processing }"
                v-bind="socialRoutes.store.form({ project: slug })"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                @success="appPassword = ''"
            >
                <UFormField
                    label="Handle"
                    name="handle"
                    :error="errors.handle"
                >
                    <UInput
                        v-model="handle"
                        name="handle"
                        placeholder="you.bsky.social"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    label="App password"
                    name="app_password"
                    :error="errors.app_password"
                >
                    <UInput
                        v-model="appPassword"
                        name="app_password"
                        type="password"
                        placeholder="xxxx-xxxx-xxxx-xxxx"
                        class="w-full"
                    />
                </UFormField>

                <UButton
                    type="submit"
                    icon="i-lucide-plug"
                    label="Connect"
                    :loading="processing"
                />
            </Form>
        </UCard>
    </div>

    <UModal
        v-model:open="editingOpen"
        title="Projects allowed to post through this account"
        :ui="{ content: 'max-w-lg' }"
    >
        <template #body>
            <Form
                v-if="editing"
                v-slot="{ processing }"
                v-bind="socialRoutes.update.form({ project: slug, socialAccount: editing.id })"
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
