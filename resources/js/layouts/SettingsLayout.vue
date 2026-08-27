<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import type { NavigationMenuItem } from '@nuxt/ui'
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import general from '@/routes/settings/organization/general'
import billing from '@/routes/settings/organization/billing'
import knowledgeBase from '@/routes/settings/knowledge-base'
import mailboxes from '@/routes/settings/mailboxes'
import members from '@/routes/settings/members'
import project from '@/routes/settings/project'

defineProps<{
    title: string
}>()

const page = usePage()

const items = computed<NavigationMenuItem[]>(() =>
    [
        { label: 'Project', icon: 'i-lucide-folder-cog', to: project.edit.url() },
        {
            label: 'Project knowledge',
            icon: 'i-lucide-book-open',
            to: knowledgeBase.edit.url()
        },
        // Organization scope rather than project: one address is often used by
        // two products and never by a third.
        { label: 'Organization', icon: 'i-lucide-building-2', to: general.edit.url() },
        { label: 'Mailboxes', icon: 'i-lucide-mail', to: mailboxes.index.url() },
        { label: 'Members', icon: 'i-lucide-users', to: members.index.url() },
        // Cloud only: self-hosted has no wallet, no plan, nothing this
        // screen would show.
        ...(page.props.edition === 'cloud'
            ? [{ label: 'Billing', icon: 'i-lucide-credit-card', to: billing.edit.url() }]
            : [])
    ].map(item => ({
        ...item,
        // `item.to` is absolute in prod (`AppServiceProvider` forces an
        // absolute root URL app-wide there) but `page.url` from Inertia is
        // always relative — strip the origin before comparing, same fix as
        // `AppLayout.vue`'s sidebar.
        active: page.url.startsWith(item.to.replace(/^https?:\/\/[^/]+/, ''))
    }))
)
</script>

<template>
    <AppLayout>
        <div class="flex h-full flex-1">
            <aside class="w-64 shrink-0 border-e border-default p-4">
                <UNavigationMenu
                    :items="items"
                    orientation="vertical"
                    :ui="{ link: 'p-1.5 overflow-hidden' }"
                />
            </aside>

            <div class="min-w-0 flex-1 space-y-4 overflow-y-auto p-4">
                <h2 class="font-medium">
                    {{ title }}
                </h2>

                <slot />
            </div>
        </div>
    </AppLayout>
</template>
