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
// Wayfinder bakes that into every generated `xxx.url()` call at build time -
// so these return absolute URLs in prod but relative ones in local dev.
// `page.url` from Inertia is always relative. Nuxt UI's own built-in
// active-link detection compares the two directly and would silently never
// match in prod, so every item needs its href's origin stripped before
// comparing - a plain `to`+`exact` pair on the item is not enough here.
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
// Two dropdowns, not one: an organization and a project are different kinds
// of choice, and the header reads left to right as org / project / section.
const currentOrganizationId = computed(() => page.props.currentProject?.organization_id ?? null)

const currentOrganization = computed(() =>
    page.props.organizations.find(organization => organization.id === currentOrganizationId.value) ?? null)

const currentOrgProjects = computed(() =>
    page.props.projects.filter(project => project.organization_id === currentOrganizationId.value))

function firstProjectOf (organizationId: number) {
    return page.props.projects.find(project => project.organization_id === organizationId)
}

// Every project's domain, shortened to a host: `new URL` throws on whatever a
// user typed before validation caught it, so a bad one just falls back to the
// full string rather than blanking the row.
function host (url: string): string {
    try {
        return new URL(url).host
    } catch {
        return url
    }
}

// Picking another organization has no "just switch context" route of its
// own: it lands on whichever of its projects sorts first, or on creating one
// when it has none yet. Picking a project in the SAME organization only ever
// does the latter. Each row carries its own avatar initial and meta line
// (`item-leading` / item `description`), rather than the plain icon+label a
// bare `DropdownMenuItem` gives you, to read the way the design has it.
const orgMenu = computed<DropdownMenuItem[][]>(() => [
    page.props.organizations.map((organization) => {
        const isCurrent = organization.id === currentOrganizationId.value
        const project = firstProjectOf(organization.id)
        const count = page.props.projects.filter(p => p.organization_id === organization.id).length

        return {
            label: organization.name,
            description: `${count} project${count === 1 ? '' : 's'}`,
            initial: organization.name.charAt(0).toUpperCase(),
            current: isCurrent,
            class: isCurrent ? 'bg-primary/10' : '',
            to: (!isCurrent && !project) ? createProject.url({ query: { organization_id: organization.id } }) : undefined,
            onSelect: (!isCurrent && project) ? () => router.put(switchProject.url(project.id)) : undefined
        }
    }),
    [
        {
            label: 'New organization',
            icon: 'i-lucide-plus',
            to: createOrganization.url()
        }
    ]
])

const projectMenu = computed<DropdownMenuItem[][]>(() => [
    currentOrgProjects.value.map((project) => {
        const isCurrent = project.id === page.props.currentProject?.id

        return {
            label: project.name,
            description: project.analyzed ? host(project.url) : undefined,
            initial: project.name.charAt(0).toUpperCase(),
            current: isCurrent,
            analyzing: !project.analyzed,
            class: isCurrent ? 'bg-primary/10' : '',
            onSelect: () => router.put(switchProject.url(project.id))
        }
    }),
    [
        {
            label: 'New project',
            icon: 'i-lucide-plus',
            to: createProject.url()
        }
    ]
])

// The header's breadcrumb tail, so the org / project switcher reads all the
// way out to where you are: "org / project / Targets", the way the design
// has it, rather than stopping at the project.
const currentSectionLabel = computed(() => items.value.find(item => item.active)?.label ?? null)

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
            :ui="{ container: 'h-full bg-[oklch(14.2%_0.005_229)]' }"
        >
            <template #header>
                <!-- The mark, not a switcher: an organization and a project
                     are chosen in the header now, breadcrumb-style, so the
                     sidebar opens on the one thing that never changes. -->
                <div class="flex items-center gap-2 overflow-hidden px-1.5 py-1">
                    <svg
                        viewBox="0 0 64 64"
                        class="size-[22px] shrink-0"
                        aria-hidden="true"
                    >
                        <rect
                            width="64"
                            height="64"
                            rx="14"
                            class="fill-primary"
                        />
                        <g fill="oklch(16.5% 0.006 228)">
                            <path d="M20 40 A12 12 0 0 1 44 40 Z" />
                            <rect
                                x="9"
                                y="39.5"
                                width="46"
                                height="5"
                                rx="2.5"
                            />
                            <rect
                                x="29.5"
                                y="14"
                                width="5"
                                height="9"
                                rx="2.5"
                            />
                            <rect
                                x="19"
                                y="15"
                                width="5"
                                height="9"
                                rx="2.5"
                                transform="rotate(-30 21.5 19.5)"
                            />
                            <rect
                                x="40"
                                y="15"
                                width="5"
                                height="9"
                                rx="2.5"
                                transform="rotate(30 42.5 19.5)"
                            />
                        </g>
                    </svg>
                    <span
                        v-if="open"
                        class="truncate text-[15px] font-semibold text-highlighted"
                    >Eveil</span>
                </div>
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
                class="flex h-[52px] shrink-0 items-center gap-1 border-b border-default px-4"
            >
                <UButton
                    icon="i-lucide-panel-left"
                    color="neutral"
                    variant="ghost"
                    aria-label="Toggle sidebar"
                    @click="open = !open"
                />

                <!-- The org / project switcher, breadcrumb-style: two
                     dropdowns and a trailing section label, so a screen
                     always reads "where you are, all the way out". -->
                <div class="flex min-w-0 flex-1 items-center gap-0.5 text-[13.5px]">
                    <UDropdownMenu
                        :items="orgMenu"
                        :ui="{ content: 'w-72' }"
                    >
                        <UButton
                            color="neutral"
                            variant="ghost"
                            size="sm"
                            trailing-icon="i-lucide-chevron-down"
                            class="min-w-0 max-w-48 gap-1.5 font-medium text-muted"
                            :ui="{ trailingIcon: 'size-3 opacity-50', label: 'truncate' }"
                        >
                            <span class="grid size-[18px] shrink-0 place-items-center rounded-[5px] bg-accented text-[9.5px] font-semibold text-toned">
                                {{ currentOrganization?.name.charAt(0).toUpperCase() }}
                            </span>
                            <span class="truncate">{{ currentOrganization?.name ?? 'No organization' }}</span>
                        </UButton>

                        <template #item-leading="{ item }">
                            <span
                                class="grid size-6 shrink-0 place-items-center rounded-md text-[11px] font-semibold"
                                :class="item.current ? 'bg-accented text-toned' : 'bg-elevated text-muted'"
                            >{{ item.initial }}</span>
                        </template>
                        <template #item-trailing="{ item }">
                            <UIcon
                                v-if="item.current"
                                name="i-lucide-check"
                                class="size-4 text-primary"
                            />
                        </template>
                    </UDropdownMenu>

                    <span class="text-dimmed">/</span>

                    <UDropdownMenu
                        :items="projectMenu"
                        :ui="{ content: 'w-72' }"
                    >
                        <UButton
                            color="neutral"
                            variant="ghost"
                            size="sm"
                            trailing-icon="i-lucide-chevron-down"
                            class="min-w-0 max-w-48 gap-1.5 font-medium text-highlighted"
                            :ui="{ trailingIcon: 'size-3 opacity-50', label: 'truncate' }"
                        >
                            <span class="grid size-[18px] shrink-0 place-items-center rounded-[5px] bg-primary text-[9.5px] font-semibold text-inverted">
                                {{ page.props.currentProject?.name.charAt(0).toUpperCase() }}
                            </span>
                            <span class="truncate">{{ page.props.currentProject?.name ?? 'No project' }}</span>
                        </UButton>

                        <template #item-leading="{ item }">
                            <span
                                class="grid size-6 shrink-0 place-items-center rounded-md text-[11px] font-semibold"
                                :class="item.current ? 'bg-primary text-inverted' : 'bg-elevated text-muted'"
                            >{{ item.initial }}</span>
                        </template>
                        <template #item-trailing="{ item }">
                            <span
                                v-if="item.analyzing"
                                class="inline-flex items-center gap-1.5 text-xs text-warning"
                            >
                                <span class="size-1 rounded-full bg-warning" />
                                Analyzing
                            </span>
                            <UIcon
                                v-else-if="item.current"
                                name="i-lucide-check"
                                class="size-4 text-primary"
                            />
                        </template>
                    </UDropdownMenu>

                    <template v-if="currentSectionLabel">
                        <span class="text-dimmed">/</span>
                        <span class="shrink-0 px-1.5 text-muted">{{ currentSectionLabel }}</span>
                    </template>
                </div>

                <slot name="header" />

                <!-- App-wide status, not page content - the header bar is
                     reserved for exactly this (`.ai/rules/js.md`). A mailbox
                     that stopped itself is easy to miss on a page that never
                     mentions mailboxes, so it gets a permanent pill here on
                     top of the full alert below. -->
                <div class="ml-auto flex items-center gap-2">
                    <UButton
                        v-if="page.props.setup?.broken?.length"
                        :to="mailboxes.index.url()"
                        :label="page.props.setup.broken.length === 1
                            ? '1 mailbox paused'
                            : `${page.props.setup.broken.length} mailboxes paused`"
                        icon="i-lucide-mail-warning"
                        color="error"
                        variant="subtle"
                        size="sm"
                        class="rounded-full"
                    />

                    <UButton
                        v-if="page.props.wallet"
                        :href="organizationBilling.edit.url()"
                        :label="`${page.props.wallet.balance.toLocaleString()} credits`"
                        icon="i-lucide-coins"
                        color="neutral"
                        variant="subtle"
                        size="sm"
                    />
                </div>
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
