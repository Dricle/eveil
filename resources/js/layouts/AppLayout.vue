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
import mailboxes from '@/routes/settings/mailboxes'
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

// App settings are a scope of their own, whoever runs the install and never
// somebody granted access through an organization, so the entry only exists
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
                <!-- Both of these stop the product working and neither announces
                     itself: without a provider key every agent dies in the
                     queue, and without a mailbox a campaign can be written and
                     activated and still never send anything. -->
                <div
                    v-if="page.props.setup?.provider || page.props.setup?.mailbox || page.props.setup?.broken?.length"
                    class="space-y-2 px-4 pt-4"
                >
                    <UAlert
                        v-if="page.props.setup?.provider"
                        color="warning"
                        variant="subtle"
                        icon="i-lucide-key-round"
                        title="No AI provider key yet"
                        description="Nothing the agents do can run without one: analysing a site, deriving segments, qualifying a company, writing a sequence. Jobs will queue and fail."
                        :actions="[{ label: 'Add the key', to: appSettings.edit.url(), color: 'warning', variant: 'solid' }]"
                    />

                    <UAlert
                        v-if="page.props.setup?.mailbox"
                        color="warning"
                        variant="subtle"
                        icon="i-lucide-mail"
                        title="No mailbox connected to this project"
                        description="Everything up to writing a sequence works, but nothing can be sent: a campaign will activate and then sit there. Connect one, and tick this project."
                        :actions="[{ label: 'Connect a mailbox', to: mailboxes.index.url(), color: 'warning', variant: 'solid' }]"
                    />

                    <!-- A mailbox that stopped itself is not missing, it is
                         broken, and nothing else on any screen says so: the
                         campaign stays active, the sequence stays due, and the
                         run simply never moves. The server's own sentence is
                         printed verbatim because it names the setting to
                         change; a paraphrase of it would not. -->
                    <UAlert
                        v-for="mailbox in page.props.setup?.broken ?? []"
                        :key="mailbox.id"
                        color="error"
                        variant="subtle"
                        icon="i-lucide-mail-warning"
                        :title="mailbox.status === 'error'
                            ? `${mailbox.email} cannot send`
                            : `${mailbox.email} has been paused`"
                        :description="mailbox.error
                            ? `The mail server said: ${mailbox.error}`
                            : 'Nothing leaves this address until it is switched back on. Sequences using it stay where they are.'"
                        :actions="[{ label: 'Fix the mailbox', to: mailboxes.index.url(), color: 'error', variant: 'solid' }]"
                    />
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>
