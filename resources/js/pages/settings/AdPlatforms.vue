<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import adRoutes from '@/routes/ads'
import platformAppRoutes from '@/routes/settings/platform-apps'

defineOptions({ layout: [AppLayout, [SettingsLayout, { title: 'Ad platforms' }]] })

type AdPlatformApp = {
    value: string
    label: string
    client_id: string | null
    has_own_app: boolean
    has_instance_app: boolean
    redirect_uri: string
    needs_developer_token: boolean
    connected_accounts: number
    docs_url: string
}

// Inline rather than a type alias imported through the barrel: an alias there
// silently declares no props at all.
const props = defineProps<{
    platforms: AdPlatformApp[]
}>()

const page = usePage()
const toast = useToast()
const slug = computed(() => page.props.currentProject!.slug)

// A local draft synced from the prop, not `default-value`: see `.ai/rules/js.md`.
const clientIds = ref<Record<string, string>>({})
const secrets = ref<Record<string, string>>({})
const developerTokens = ref<Record<string, string>>({})
watch(() => props.platforms, (platforms) => {
    clientIds.value = Object.fromEntries(platforms.map(platform => [platform.value, platform.client_id ?? '']))
}, { immediate: true, deep: true })

/**
 * Where a network stands for this project: no app to connect through yet,
 * an app but no account connected, or accounts connected.
 */
function stateOf (platform: AdPlatformApp) {
    if (platform.connected_accounts > 0) {
        return {
            icon: 'i-lucide-circle-check',
            color: 'success' as const,
            label: `${platform.connected_accounts} account${platform.connected_accounts > 1 ? 's' : ''} connected`
        }
    }

    if (platform.has_own_app || platform.has_instance_app) {
        return { icon: 'i-lucide-circle-dot', color: 'warning' as const, label: 'Ready to connect' }
    }

    return { icon: 'i-lucide-circle-dashed', color: 'neutral' as const, label: 'Not set up' }
}

// Opens on the first network that still needs something, else the first.
const activeTab = ref(
    (props.platforms.find(platform => platform.connected_accounts === 0) ?? props.platforms[0])?.value
)

function copyRedirectUri (platform: AdPlatformApp) {
    navigator.clipboard.writeText(platform.redirect_uri)
    toast.add({ title: 'Redirect URI copied', color: 'neutral' })
}

function remove (platform: AdPlatformApp) {
    router.delete(platformAppRoutes.destroy.url({ project: slug.value, platform: platform.value }), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: `${platform.label} app removed`, color: 'neutral' })
    })
}
</script>

<template>
    <Head title="Ad platforms" />

    <div class="space-y-6">
        <p class="text-sm text-muted">
            Each ad network only lets an approved developer app manage ads. Set
            up your own app once per network, then connect this project's ad
            accounts.
        </p>

        <div
            class="grid gap-3 sm:grid-cols-3"
            role="tablist"
        >
            <button
                v-for="platform in platforms"
                :key="platform.value"
                type="button"
                role="tab"
                :aria-selected="activeTab === platform.value"
                class="flex items-center gap-3 rounded-lg p-3 text-left ring transition"
                :class="activeTab === platform.value ? 'ring-primary' : 'ring-default hover:ring-accented'"
                @click="activeTab = platform.value"
            >
                <UIcon
                    :name="stateOf(platform).icon"
                    class="size-5 shrink-0"
                    :class="{
                        'text-success': stateOf(platform).color === 'success',
                        'text-warning': stateOf(platform).color === 'warning',
                        'text-dimmed': stateOf(platform).color === 'neutral'
                    }"
                />
                <span class="min-w-0">
                    <span class="block font-medium">{{ platform.label }}</span>
                    <span class="block text-sm text-muted">{{ stateOf(platform).label }}</span>
                </span>
            </button>
        </div>

        <!-- v-show, not v-if: a half-typed secret survives a look at another network. -->
        <template
            v-for="platform in platforms"
            :key="platform.value"
        >
            <div
                v-show="activeTab === platform.value"
                class="space-y-6"
            >
                <div class="flex flex-wrap items-center gap-3 rounded-lg p-4 ring ring-default">
                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <UBadge
                                :color="stateOf(platform).color"
                                variant="subtle"
                                :icon="stateOf(platform).icon"
                                :label="stateOf(platform).label"
                            />
                            <UBadge
                                v-if="platform.has_own_app"
                                color="neutral"
                                variant="outline"
                                label="Your own app"
                            />
                            <UBadge
                                v-else-if="platform.has_instance_app"
                                color="neutral"
                                variant="outline"
                                label="Using this instance's app"
                            />
                        </div>
                        <p class="text-sm text-muted">
                            <template v-if="platform.connected_accounts > 0">
                                Eveil can run ads on this network. Manage the accounts and the budget on the Ads page.
                            </template>
                            <template v-else-if="platform.has_own_app || platform.has_instance_app">
                                The app is ready: connect this project's ad accounts.
                            </template>
                            <template v-else>
                                Create an app on {{ platform.label }}, paste its credentials below, then connect.
                            </template>
                        </p>
                    </div>

                    <UButton
                        v-if="platform.has_own_app || platform.has_instance_app"
                        :href="adRoutes.connect.url({ project: slug, platform: platform.value })"
                        external
                        :label="platform.connected_accounts > 0 ? 'Connect another account' : 'Connect ad accounts'"
                        :variant="platform.connected_accounts > 0 ? 'outline' : 'solid'"
                        icon="i-lucide-plug"
                    />
                    <UButton
                        :href="platform.docs_url"
                        target="_blank"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-book-open"
                        label="Setup guide"
                    />
                </div>

                <Form
                    v-slot="{ errors, processing }"
                    v-bind="platformAppRoutes.update.form({ project: slug, platform: platform.value })"
                    class="space-y-4"
                    :options="{ preserveScroll: true }"
                    @success="secrets[platform.value] = ''; developerTokens[platform.value] = ''; toast.add({ title: `${platform.label} app saved`, color: 'success' })"
                >
                    <h3 class="text-sm font-medium text-muted">
                        Your app
                    </h3>

                    <UFormField
                        label="Redirect URI"
                        help="Register it exactly as is on the network's developer console."
                    >
                        <UInput
                            :model-value="platform.redirect_uri"
                            readonly
                            class="w-full"
                            :ui="{ trailing: 'pe-1' }"
                        >
                            <template #trailing>
                                <UButton
                                    icon="i-lucide-copy"
                                    color="neutral"
                                    variant="link"
                                    size="sm"
                                    aria-label="Copy the redirect URI"
                                    @click="copyRedirectUri(platform)"
                                />
                            </template>
                        </UInput>
                    </UFormField>

                    <UFormField
                        label="Client ID"
                        :error="errors.client_id"
                    >
                        <UInput
                            v-model="clientIds[platform.value]"
                            name="client_id"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        label="Client secret"
                        :error="errors.client_secret"
                        :hint="platform.has_own_app ? 'Leave blank to keep the current one' : undefined"
                    >
                        <UInput
                            v-model="secrets[platform.value]"
                            name="client_secret"
                            type="password"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        v-if="platform.needs_developer_token"
                        label="Developer token"
                        :error="errors.developer_token"
                        :hint="platform.has_own_app ? 'Leave blank to keep the current one' : undefined"
                    >
                        <UInput
                            v-model="developerTokens[platform.value]"
                            name="developer_token"
                            type="password"
                            class="w-full"
                        />
                    </UFormField>

                    <div class="flex gap-2">
                        <UButton
                            type="submit"
                            label="Save"
                            :loading="processing"
                        />
                        <UButton
                            v-if="platform.has_own_app"
                            color="error"
                            variant="ghost"
                            label="Remove"
                            @click="remove(platform)"
                        />
                    </div>
                </Form>
            </div>
        </template>
    </div>
</template>
