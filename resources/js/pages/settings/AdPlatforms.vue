<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import platformAppRoutes from '@/routes/settings/platform-apps'

defineOptions({ layout: [AppLayout, [SettingsLayout, { title: 'Ad platforms' }]] })

type AdPlatformApp = {
    value: string
    label: string
    client_id: string | null
    has_own_app: boolean
    has_instance_app: boolean
    redirect_uri: string
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
watch(() => props.platforms, (platforms) => {
    clientIds.value = Object.fromEntries(platforms.map(platform => [platform.value, platform.client_id ?? '']))
}, { immediate: true, deep: true })

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
            Each ad network only lets an approved developer app manage ads. Use
            your own app here: its access, quotas and review are yours alone, so
            nobody else's ads can get it restricted. Register the redirect URI
            below on the network's console, then connect your ad accounts from
            the Ads page.
            <a
                href="https://docs.eveil.cloud/product/ads#google-ads-getting-a-client-id-and-secret"
                target="_blank"
                class="text-primary underline"
            >How to get a client ID and secret</a>
        </p>

        <UCard
            v-for="platform in platforms"
            :key="platform.value"
        >
            <template #header>
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-medium">
                        {{ platform.label }}
                    </h3>
                    <UBadge
                        v-if="platform.has_own_app"
                        color="success"
                        variant="subtle"
                        label="Your own app"
                    />
                    <UBadge
                        v-else-if="platform.has_instance_app"
                        color="neutral"
                        variant="subtle"
                        label="Using this instance's app"
                    />
                </div>
            </template>

            <Form
                v-slot="{ errors, processing }"
                v-bind="platformAppRoutes.update.form({ project: slug, platform: platform.value })"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                @success="secrets[platform.value] = ''; toast.add({ title: `${platform.label} app saved`, color: 'success' })"
            >
                <UFormField label="Redirect URI">
                    <UInput
                        :model-value="platform.redirect_uri"
                        readonly
                        class="w-full"
                    />
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
        </UCard>
    </div>
</template>
