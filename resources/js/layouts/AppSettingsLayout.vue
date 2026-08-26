<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import type { NavigationMenuItem } from '@nuxt/ui'
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import agents from '@/routes/app-settings/agents'
import billing from '@/routes/app-settings/billing'
import emailExamples from '@/routes/app-settings/email-examples'
import hosts from '@/routes/app-settings/hosts'
import limits from '@/routes/app-settings/limits'
import provider from '@/routes/app-settings/provider'
import sending from '@/routes/app-settings/sending'

defineProps<{
    title: string
}>()

const page = usePage()

const items = computed<NavigationMenuItem[]>(() =>
    [
        {
            label: 'AI provider',
            icon: 'i-lucide-key-round',
            to: provider.edit.url()
        },
        { label: 'Agents', icon: 'i-lucide-bot', to: agents.index.url() },
        { label: 'Limits', icon: 'i-lucide-gauge', to: limits.edit.url() },
        { label: 'Sending', icon: 'i-lucide-send', to: sending.edit.url() },
        { label: 'Host registry', icon: 'i-lucide-globe', to: hosts.index.url() },
        { label: 'Email examples', icon: 'i-lucide-mail-plus', to: emailExamples.index.url() },
        // `billing.*` is never read on self-hosted (`.ai/rules/cloud.md`), so
        // the tab itself only exists where the settings would do anything.
        ...(page.props.edition === 'cloud'
            ? [{ label: 'Billing', icon: 'i-lucide-credit-card', to: billing.edit.url() }]
            : [])
    ].map(item => ({
        ...item,
        // `item.to` is absolute in prod (`AppServiceProvider` forces an
        // absolute root URL app-wide there) but `page.url` from Inertia is
        // always relative — strip the origin before comparing, same fix as
        // `AppLayout.vue`'s and `SettingsLayout.vue`'s sidebars.
        active: page.url.startsWith(item.to.replace(/^https?:\/\/[^/]+/, ''))
    }))
)
</script>

<template>
    <AppLayout>
        <div class="flex h-full flex-1">
            <aside class="w-64 shrink-0 border-e border-default p-4">
                <p class="mb-3 px-1.5 text-xs text-dimmed">
                    Applies to every project on this install.
                </p>

                <UNavigationMenu
                    :items="items"
                    orientation="vertical"
                    :ui="{ link: 'p-1.5 overflow-hidden' }"
                />
            </aside>

            <div class="min-w-0 flex-1 space-y-4 p-4">
                <h2 class="font-medium">
                    {{ title }}
                </h2>

                <UAlert
                    v-if="page.props.status"
                    color="primary"
                    variant="subtle"
                    icon="i-lucide-check"
                    :description="page.props.status"
                />

                <slot />
            </div>
        </div>
    </AppLayout>
</template>
