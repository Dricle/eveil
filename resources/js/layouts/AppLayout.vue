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
import { create as createProject } from '@/routes/projects'
import { create as createOrganization } from '@/routes/organizations'
import mailboxes from '@/routes/settings/mailboxes'
import organizationBilling from '@/routes/settings/organization/billing'
import projectSettings from '@/routes/settings/project'
import targets from '@/routes/targets'

const page = usePage()

const open = ref(true)

// AppServiceProvider forces an absolute root URL for the whole app in prod
// (needed so a password-reset email doesn't link to plain http), and
// Wayfinder bakes that into every generated `xxx.url()` call at build time —
// so these return absolute URLs in prod but relative ones in local dev.
// `page.url` from Inertia is always relative. Nuxt UI's own built-in
// active-link detection compares the two directly and would silently never
// match in prod, so every item needs its href's origin stripped before
// comparing — a plain `to`+`exact` pair on the item is not enough here.
function isCurrent (path: string): boolean {
    return page.url.startsWith(path.replace(/^https?:\/\/[^/]+/, ''))
}

const items = computed<NavigationMenuItem[]>(() => [
    {
        label: 'Dashboard',
        icon: 'i-lucide-house',
        to: dashboard.url(),
        active: page.url === dashboard.url().replace(/^https?:\/\/[^/]+/, '')
    },
    {
        label: 'Targets',
        icon: 'i-lucide-crosshair',
        to: targets.index.url(),
        active: isCurrent(targets.index.url()) || page.url.startsWith('/app/discovery-runs')
    },
    {
        label: 'Leads',
        icon: 'i-lucide-building-2',
        to: companies.index.url(),
        active: isCurrent(companies.index.url())
    },
    {
        label: 'Campaigns',
        icon: 'i-lucide-send',
        to: campaigns.index.url(),
        active: isCurrent(campaigns.index.url())
    },
    {
        label: 'Inbox',
        icon: 'i-lucide-inbox',
        to: inbox.url(),
        active: isCurrent(inbox.url())
    },
    {
        label: 'Settings',
        icon: 'i-lucide-settings',
        to: projectSettings.edit.url(),
        // Broad on purpose: mailboxes, billing, members and other settings
        // pages all live under this one prefix, not just the project-edit page.
        active: page.url.startsWith('/app/settings')
    }
])

// The project list is the switcher, not a nav entry: every screen below the
// dashboard belongs to one project, so choosing it is context, not navigation.
//
// One popover for both levels rather than two separate switchers: the
// current organization's projects, "new project", a separator, every OTHER
// organization the user is in (switching to whichever project of theirs
// sorts first, or straight to project creation if it has none yet), "new
// organization" last.
const currentOrganizationId = computed(() => page.props.currentProject?.organization_id ?? null)

const otherOrganizations = computed(() =>
    page.props.organizations.filter(organization => organization.id !== currentOrganizationId.value))

function firstProjectOf (organizationId: number) {
    return page.props.projects.find(project => project.organization_id === organizationId)
}

const projectMenu = computed<DropdownMenuItem[][]>(() => {
    const currentOrgProjects = page.props.projects.filter(project => project.organization_id === currentOrganizationId.value)

    return [
        currentOrgProjects.map(project => ({
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
                to: createProject.url()
            }
        ],
        otherOrganizations.value.map((organization) => {
            const project = firstProjectOf(organization.id)

            return {
                label: organization.name,
                icon: 'i-lucide-building-2',
                to: project
                    ? undefined
                    : createProject.url({ query: { organization_id: organization.id } }),
                onSelect: project ? () => router.put(switchProject.url(project.id)) : undefined
            }
        }),
        [
            {
                label: 'New organization',
                icon: 'i-lucide-plus',
                to: createOrganization.url()
            }
        ]
    ]
})

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
                        color="neutral"
                        variant="ghost"
                        block
                        class="justify-start overflow-hidden"
                    >
                        <UIcon
                            name="i-lucide-folder"
                            class="size-5 shrink-0"
                        />
                        <span
                            v-if="open"
                            class="min-w-0 flex-1 text-left leading-tight"
                        >
                            <span class="block truncate font-semibold">{{ page.props.currentProject?.name ?? 'No project' }}</span>
                            <span
                                v-if="page.props.currentProject"
                                class="block truncate text-xs text-muted"
                            >{{ page.props.currentProject.organization_name }}</span>
                        </span>
                        <UIcon
                            v-if="open"
                            name="i-lucide-chevrons-up-down"
                            class="size-4 shrink-0 text-muted"
                        />
                    </UButton>
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

                <!-- App-wide status, not page content — the header bar is
                     reserved for exactly this (`.ai/rules/js.md`). -->
                <UButton
                    v-if="page.props.wallet"
                    :href="organizationBilling.edit.url()"
                    :label="`${page.props.wallet.balance.toLocaleString()} credits`"
                    icon="i-lucide-coins"
                    color="neutral"
                    variant="subtle"
                    size="sm"
                    class="ml-auto"
                />
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
