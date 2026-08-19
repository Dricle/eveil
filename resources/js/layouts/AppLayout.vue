<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import type { DropdownMenuItem, NavigationMenuItem } from '@nuxt/ui'
import { computed, ref } from 'vue'
import { dashboard, inbox, logout } from '@/routes'
import campaigns from '@/routes/campaigns'
import companies from '@/routes/companies'
import { profile } from '@/routes/account'
import { update as switchProject } from '@/routes/current-project'
import appSettings from '@/routes/app-settings/provider'
import { create } from '@/routes/projects'
import projectSettings from '@/routes/settings/project'
import targets from '@/routes/targets'

const page = usePage()

const open = ref(true)

const items = computed<NavigationMenuItem[]>(() => [
    {
        label: 'Dashboard',
        icon: 'i-lucide-house',
        to: dashboard.url(),
        active: page.url === dashboard.url()
    },
    {
        label: 'Targets',
        icon: 'i-lucide-crosshair',
        to: targets.index.url(),
        active: page.url.startsWith(targets.index.url()) || page.url.startsWith('/app/discovery-runs')
    },
    {
        label: 'Leads',
        icon: 'i-lucide-building-2',
        to: companies.index.url(),
        active: page.url.startsWith(companies.index.url())
    },
    {
        label: 'Campaigns',
        icon: 'i-lucide-send',
        to: campaigns.index.url(),
        active: page.url.startsWith(campaigns.index.url())
    },
    {
        label: 'Inbox',
        icon: 'i-lucide-inbox',
        to: inbox.url(),
        active: page.url.startsWith(inbox.url())
    },
    {
        label: 'Settings',
        icon: 'i-lucide-settings',
        to: projectSettings.edit.url(),
        active: page.url.startsWith('/app/settings')
    }
])

// The project list is the switcher, not a nav entry: every screen below the
// dashboard belongs to one project, so choosing it is context, not navigation.
const projectMenu = computed<DropdownMenuItem[][]>(() => [
    page.props.projects.map(project => ({
        label: project.name,
        icon: project.id === page.props.currentProject?.id
            ? 'i-lucide-check'
            : 'i-lucide-folder',
        onSelect: () => router.put(switchProject.url(project.id))
    })),
    [
        {
            label: 'New project',
            icon: 'i-lucide-plus',
            to: create.url()
        }
    ]
])

// App settings are a scope of their own — whoever runs the install, never
// somebody granted access through an organization — so the entry only exists
// for them and hangs off the user menu rather than the project nav. "Settings"
// in the sidebar is the current project's; this one is the whole app's.
const userMenu = computed<DropdownMenuItem[][]>(() => [
    [
        { label: 'Account', icon: 'i-lucide-user', to: profile.url() },
        ...(page.props.auth.user.is_super_admin
            ? [{ label: 'App settings', icon: 'i-lucide-server-cog', to: appSettings.edit.url() }]
            : []),
        {
            label: 'Log out',
            icon: 'i-lucide-log-out',
            onSelect: () => router.post(logout.url())
        }
    ]
])
</script>

<template>
    <!-- h-screen rather than the docs' flex-1: this layout IS the page frame,
         there is no outer flex parent to grow inside of. -->
    <div class="flex h-screen w-full">
        <USidebar
            v-model:open="open"
            collapsible="icon"
            :ui="{ container: 'h-full' }"
        >
            <template #header>
                <UDropdownMenu
                    :items="projectMenu"
                    class="w-full"
                >
                    <UButton
                        :label="page.props.currentProject?.name ?? 'No project'"
                        icon="i-lucide-folder"
                        trailing-icon="i-lucide-chevrons-up-down"
                        color="neutral"
                        variant="ghost"
                        block
                        class="justify-start overflow-hidden font-semibold"
                    />
                </UDropdownMenu>
            </template>

            <UNavigationMenu
                :items="items"
                orientation="vertical"
                :ui="{ link: 'p-1.5 overflow-hidden' }"
            />

            <template #footer>
                <UDropdownMenu
                    :items="userMenu"
                    class="w-full"
                >
                    <UButton
                        :label="page.props.auth.user.name"
                        icon="i-lucide-user"
                        color="neutral"
                        variant="ghost"
                        block
                        class="justify-start overflow-hidden"
                    />
                </UDropdownMenu>
            </template>
        </USidebar>

        <div class="flex flex-1 flex-col overflow-hidden bg-default">
            <div
                class="flex h-(--ui-header-height) shrink-0 items-center gap-2 border-b border-default px-4"
            >
                <UButton
                    icon="i-lucide-panel-left"
                    color="neutral"
                    variant="ghost"
                    aria-label="Toggle sidebar"
                    @click="open = !open"
                />

                <slot name="header" />
            </div>

            <div class="flex-1 overflow-y-auto">
                <slot />
            </div>
        </div>
    </div>
</template>
