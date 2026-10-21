<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import composioRoutes from '@/routes/settings/composio'
import instagramRoutes from '@/routes/settings/instagram'
import integrationRoutes from '@/routes/settings/integrations'
import platformAppRoutes from '@/routes/settings/platform-apps'
import socialAccountRoutes from '@/routes/settings/social-accounts'
import type { Project, SocialAccount } from '@/types'

defineOptions({ layout: [AppLayout, [SettingsLayout, { title: 'Instagram' }]] })

// Inline rather than a type alias imported through the barrel: an alias there
// silently declares no props at all.
const props = defineProps<{
    accounts: SocialAccount[]
    projects: Project[]
    /** Whether the organization has a Composio key: without it, Composio cannot connect. */
    configured: boolean
    /** The organization's own Meta app: its client id if it saved one, and whether any (its own or the instance's) is usable. */
    metaApp: { client_id: string | null, available: boolean, redirect_uri: string }
}>()

const page = usePage()
const toast = useToast()
const slug = computed(() => page.props.currentProject!.slug)

// A local draft synced from the prop, not `default-value`: see `.ai/rules/js.md`.
const metaClientId = ref('')
watch(() => props.metaApp.client_id, value => metaClientId.value = value ?? '', { immediate: true })

const editing = ref<SocialAccount | null>(null)
const editingOpen = ref(false)
const editingProjects = ref<number[]>([])

function open (account: SocialAccount) {
    editing.value = account
    editingProjects.value = [...(account.projects ?? [])]
    editingOpen.value = true
}

function destroy (account: SocialAccount) {
    router.delete(socialAccountRoutes.destroy.url({ project: slug.value, socialAccount: account.id }), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Account disconnected', color: 'neutral' })
    })
}

// Drops one way in and keeps the account on the other.
function dropConnection (account: SocialAccount, connection: 'meta' | 'composio') {
    router.delete(instagramRoutes.connections.destroy.url({ project: slug.value, socialAccount: account.id, connection }), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: connection === 'meta' ? 'No longer publishing through your Meta app' : 'Composio connection removed', color: 'neutral' })
    })
}

function removeMetaApp () {
    router.delete(platformAppRoutes.destroy.url({ project: slug.value, platform: 'instagram' }), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Meta app removed', color: 'neutral' })
    })
}

function copyRedirectUri () {
    navigator.clipboard.writeText(props.metaApp.redirect_uri)
    toast.add({ title: 'Redirect URI copied', color: 'success' })
}

function expires (date: string | null | undefined): string {
    return date ? new Date(date).toLocaleDateString() : ''
}

const STATUS = {
    active: { color: 'success' as const, label: 'Active' },
    expired: { color: 'warning' as const, label: 'Reconnect needed' },
    error: { color: 'error' as const, label: 'Reconnect needed' }
}
</script>

<template>
    <Head title="Instagram" />

    <div class="space-y-6">
        <div class="space-y-2">
            <p class="text-sm text-muted">
                Instagram posts are published with an image Eveil generates for
                each one. Only Business and Creator accounts can publish: switch a
                personal account in Instagram under Settings, Account type and
                tools. The posting cadence is set from the Instagram posts queue.
            </p>
            <p class="text-sm text-muted">
                There are two ways to connect an account. <strong>Composio</strong> is
                the quick one: no app to create. <strong>Your own Meta app</strong> takes
                more setup but no third party sits in between. An account can be
                connected both ways: <strong>your Meta app always takes priority</strong>,
                and Composio is only used when the Meta app cannot publish.
            </p>

            <UAlert
                v-if="page.props.status"
                color="neutral"
                variant="subtle"
                icon="i-lucide-info"
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
                class="space-y-3 rounded-lg p-4 ring ring-default"
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
                        aria-label="Disconnect this account entirely"
                        @click="destroy(account)"
                    />
                </div>

                <!-- Its ways in, and which one publishes right now. -->
                <div class="space-y-1 text-sm">
                    <div
                        v-if="account.via_meta_app"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <UBadge
                            :color="account.publishes_through === 'meta' ? 'primary' : 'neutral'"
                            variant="subtle"
                            :label="account.publishes_through === 'meta' ? 'Publishing through your Meta app' : 'Your Meta app: token expired, reconnect it'"
                        />
                        <span
                            v-if="account.meta_token_expires_at"
                            class="text-dimmed"
                        >renewed automatically, valid until {{ expires(account.meta_token_expires_at) }}</span>
                        <UButton
                            color="neutral"
                            variant="link"
                            size="xs"
                            label="Stop using it"
                            @click="dropConnection(account, 'meta')"
                        />
                    </div>

                    <div
                        v-if="account.via_composio"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <UBadge
                            :color="account.publishes_through === 'composio' ? 'primary' : 'neutral'"
                            variant="subtle"
                            :label="account.publishes_through === 'composio' ? 'Publishing through Composio' : 'Composio: fallback only'"
                        />
                        <UButton
                            color="neutral"
                            variant="link"
                            size="xs"
                            label="Remove"
                            @click="dropConnection(account, 'composio')"
                        />
                    </div>
                </div>

                <p
                    v-if="account.last_error"
                    class="text-sm text-error"
                >
                    {{ account.last_error }}
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
                No Instagram account connected yet.
            </p>
        </div>

        <UCard>
            <template #header>
                <h3 class="font-medium">
                    Connect through Composio
                </h3>
                <p class="mt-1 text-sm text-muted">
                    The quick way: Composio's Meta app is already approved, you
                    only authorize your account on its page. Used as the fallback
                    when an account is also connected through your own Meta app.
                </p>
            </template>

            <UAlert
                v-if="!configured"
                class="mb-4"
                color="warning"
                variant="subtle"
                icon="i-lucide-triangle-alert"
                title="Add your Composio key first"
                description="Composio connects through your organization's own Composio account. It takes a few minutes and is free."
                :actions="[{ label: 'Set up Composio', href: composioRoutes.edit.url({ project: slug }), color: 'warning', variant: 'outline' }]"
            />

            <!-- A real redirect to Composio's page, not an Inertia visit. -->
            <UButton
                :href="integrationRoutes.connect.url({ project: slug, toolkit: 'instagram' })"
                external
                icon="i-lucide-plug"
                label="Connect through Composio"
                :disabled="!configured"
            />
        </UCard>

        <UCard>
            <template #header>
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-medium">
                        Connect through your own Meta app
                    </h3>
                    <UBadge
                        color="primary"
                        variant="subtle"
                        label="Takes priority over Composio"
                    />
                </div>
                <p class="mt-1 text-sm text-muted">
                    No third party in between. Create an app on Meta's developer
                    site with the Instagram product, add your account as a tester,
                    and paste its Instagram app id and secret here. Meta fetches each
                    image itself, so this instance's images must be reachable from the
                    internet. Step by step in the
                    <a
                        href="https://docs.eveil.cloud/product/social#connecting-instagram-through-your-own-meta-app"
                        target="_blank"
                        rel="noopener"
                        class="text-primary"
                    >documentation</a>.
                </p>
            </template>

            <div class="space-y-4">
                <UFormField
                    label="Redirect URI"
                    description="Add it under Instagram, Business login settings, OAuth redirect URIs, in your Meta app."
                >
                    <div class="flex max-w-xl items-center gap-2">
                        <UInput
                            :model-value="metaApp.redirect_uri"
                            readonly
                            class="w-full"
                        />
                        <UButton
                            icon="i-lucide-copy"
                            color="neutral"
                            variant="subtle"
                            aria-label="Copy the redirect URI"
                            @click="copyRedirectUri"
                        />
                    </div>
                </UFormField>

                <Form
                    v-slot="{ errors, processing }"
                    v-bind="platformAppRoutes.update.form({ project: slug, platform: 'instagram' })"
                    class="space-y-4"
                    :options="{ preserveScroll: true }"
                    @success="toast.add({ title: 'Meta app saved', color: 'success' })"
                >
                    <UFormField
                        label="Instagram app ID"
                        name="client_id"
                        :error="errors.client_id"
                    >
                        <UInput
                            v-model="metaClientId"
                            name="client_id"
                            class="w-full max-w-md"
                        />
                    </UFormField>

                    <UFormField
                        label="Instagram app secret"
                        name="client_secret"
                        :description="metaApp.client_id ? 'Leave empty to keep the saved one.' : undefined"
                        :error="errors.client_secret"
                    >
                        <UInput
                            name="client_secret"
                            type="password"
                            class="w-full max-w-md"
                        />
                    </UFormField>

                    <div class="flex flex-wrap gap-2">
                        <UButton
                            type="submit"
                            :loading="processing"
                            label="Save the app"
                        />
                        <UButton
                            v-if="metaApp.client_id"
                            color="error"
                            variant="ghost"
                            label="Remove the app"
                            @click="removeMetaApp"
                        />
                    </div>
                </Form>

                <UButton
                    :href="instagramRoutes.meta.connect.url({ project: slug })"
                    external
                    icon="i-lucide-plug"
                    label="Connect through your Meta app"
                    :disabled="!metaApp.available"
                />
            </div>
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
                v-bind="socialAccountRoutes.update.form({ project: slug, socialAccount: editing.id })"
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
