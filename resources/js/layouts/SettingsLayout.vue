<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import type { NavigationMenuItem } from '@nuxt/ui'
import { computed } from 'vue'
import general from '@/routes/settings/organization/general'
import billing from '@/routes/settings/organization/billing'
import knowledgeBase from '@/routes/settings/knowledge-base'
import linkedin from '@/routes/settings/linkedin'
import mailboxes from '@/routes/settings/mailboxes'
import members from '@/routes/settings/members'
import project from '@/routes/settings/project'
import aiInstructions from '@/routes/settings/ai-instructions'

defineProps<{
    title: string
}>()

const page = usePage()

// `item.to` is absolute in prod (`AppServiceProvider` forces an absolute
// root URL app-wide there) but `page.url` from Inertia is always relative -
// strip the origin before comparing, same fix as `AppLayout.vue`'s sidebar.
function withActive (item: NavigationMenuItem): NavigationMenuItem {
    return {
        ...item,
        active: page.url.startsWith((item.to as string).replace(/^https?:\/\/[^/]+/, ''))
    }
}

// Two groups: what belongs to THIS product (project scope) versus what
// belongs to the organization behind it - a mailbox or a LinkedIn account is
// often shared across several products, project name/knowledge never is.
const projectItems = computed<NavigationMenuItem[]>(() => [
    { label: 'Project', icon: 'i-lucide-folder-cog', to: project.edit.url() },
    { label: 'Project knowledge', icon: 'i-lucide-book-open', to: knowledgeBase.edit.url() },
    { label: 'AI instructions', icon: 'i-lucide-sparkles', to: aiInstructions.edit.url() }
].map(withActive))

const organizationItems = computed<NavigationMenuItem[]>(() => [
    { label: 'Organization', icon: 'i-lucide-building-2', to: general.edit.url() },
    { label: 'Mailboxes', icon: 'i-lucide-mail', to: mailboxes.index.url() },
    // No brand icon available (only the `lucide` icon set is installed, and
    // it carries no LinkedIn glyph): a generic one rather than a broken
    // reference.
    { label: 'LinkedIn', icon: 'i-lucide-share-2', to: linkedin.index.url() },
    { label: 'Members', icon: 'i-lucide-users', to: members.index.url() },
    // Cloud only: self-hosted has no wallet, no plan, nothing this screen
    // would show.
    ...(page.props.edition === 'cloud'
        ? [{ label: 'Billing', icon: 'i-lucide-credit-card', to: billing.edit.url() }]
        : [])
].map(withActive))
</script>

<template>
    <div class="flex h-full flex-1">
        <aside class="w-64 shrink-0 space-y-4 border-e border-default p-4">
            <div>
                <div class="px-2 pt-1 pb-1 font-mono text-[10px] font-medium tracking-wider text-dimmed uppercase">
                    Project
                </div>
                <UNavigationMenu
                    :items="projectItems"
                    orientation="vertical"
                    :ui="{ link: 'p-1.5 overflow-hidden' }"
                />
            </div>

            <div>
                <div class="px-2 pt-1 pb-1 font-mono text-[10px] font-medium tracking-wider text-dimmed uppercase">
                    Organization
                </div>
                <UNavigationMenu
                    :items="organizationItems"
                    orientation="vertical"
                    :ui="{ link: 'p-1.5 overflow-hidden' }"
                />
            </div>
        </aside>

        <div class="min-w-0 flex-1 space-y-4 overflow-y-auto p-4">
            <h2 class="font-medium">
                {{ title }}
            </h2>

            <slot />
        </div>
    </div>
</template>
