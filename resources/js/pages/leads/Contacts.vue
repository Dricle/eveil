<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import type { TableColumn } from '@nuxt/ui'
import { ref, watch } from 'vue'
import LeadsLayout from '@/layouts/LeadsLayout.vue'
import StatusSelect from '@/components/StatusSelect.vue'
import { OUTREACH_STATUSES } from '@/lib/status'
import { useTableQuery } from '@/lib/table'
import contactRoutes from '@/routes/contacts'
import type { Contact, Paginated } from '@/types'

// Inline, not a type alias: see the note in Companies.vue — an alias imported
// through the barrel silently declares no props at all.
const props = defineProps<{
    contacts: Paginated<Contact>
    filters: {
        email_status: string | null
        email_source: string | null
        company: number | null
        search: string | null
        filter: Record<string, string>
        sort: string | null
        direction: string | null
    }
    counts: Record<string, number>
    import?: {
        imported: number
        duplicates: number
        rejected: { line: number, value: string, reason: string }[]
        rejected_count: number
        truncated: boolean
    } | null
}>()

// `all` rather than an empty string: reka reserves '' for clearing a select, and
// a SelectItem carrying it throws on mount.
const status = ref(props.filters.email_status ?? 'all')
const source = ref(props.filters.email_source ?? 'all')

const table = useTableQuery(
    contactRoutes.index.url(),
    props.filters,
    ['contacts', 'filters', 'counts'],
    () => ({
        email_status: status.value === 'all' ? undefined : status.value,
        email_source: source.value === 'all' ? undefined : source.value,
        company: props.filters.company ?? undefined
    })
)

watch([status, source], () => table.reload())

// What the verification actually established, said plainly. A guessed address
// that nobody has confirmed is worth sending to — but the person sending is the
// one whose domain takes the complaints, so it never poses as a checked one.
const STATUS = {
    valid: { color: 'success' as const, label: 'Verified', help: 'The server accepted the address.' },
    unknown: { color: 'neutral' as const, label: 'Unverified', help: 'The provider blocks checks — Gmail and Outlook always do.' },
    risky: { color: 'warning' as const, label: 'Catch-all', help: 'The domain accepts everything, so acceptance proves nothing.' },
    invalid: { color: 'error' as const, label: 'Invalid', help: 'Rejected by the server. Never sent to.' }
}

const SOURCE = {
    scraped: 'Published on the site',
    inferred: 'Guessed from another address on the domain',
    provided: 'Given by the user',
    imported: 'Imported'
}

const STATUS_OPTIONS = [
    { label: 'Everything sendable', value: 'all' },
    { label: 'Verified', value: 'valid' },
    { label: 'Unverified', value: 'unknown' },
    { label: 'Catch-all', value: 'risky' },
    { label: 'Invalid', value: 'invalid' }
]

const SOURCE_OPTIONS = [
    { label: 'Any source', value: 'all' },
    { label: 'Published on the site', value: 'scraped' },
    { label: 'Guessed from a pattern', value: 'inferred' },
    { label: 'Given by the user', value: 'provided' },
    { label: 'Imported', value: 'imported' }
]

const COLUMNS = [
    { key: 'name', label: 'Name', sortable: true, filterable: true },
    { key: 'title', label: 'Role', sortable: true, filterable: true },
    { key: 'email', label: 'Email', sortable: true, filterable: true },
    { key: 'email_status', label: 'Verification', sortable: true, filterable: false },
    { key: 'email_source', label: 'Source', sortable: true, filterable: false },
    { key: 'company', label: 'Company', sortable: true, filterable: true },
    { key: 'discovered_at', label: 'Found', sortable: true, filterable: false },
    { key: 'status', label: 'Status', sortable: true, filterable: false }
]

const columns: TableColumn<Contact>[] = COLUMNS.map(column => ({
    accessorKey: column.key,
    header: column.label
}))

// Precomputed: a dynamic slot name may not contain quotes, so it cannot be
// built inline in the template.
const headerSlots = COLUMNS.map(column => ({ ...column, slot: `${column.key}-header` }))

const FILTERABLE = COLUMNS.filter(column => column.filterable)

// Open when the person arrived with a column filter already applied, so a
// narrowed list never looks unfiltered.
const columnFilters = ref(Object.keys(props.filters.filter ?? {}).length > 0)

function day (value: string) {
    return new Date(value).toLocaleDateString()
}

// `row.original` reaches a slot untyped, so the lookups happen behind a typed
// parameter rather than in the template.
function verdict (contact: Contact) {
    return contact.email_status === null ? null : STATUS[contact.email_status]
}

function sourceLabel (contact: Contact) {
    return contact.email_source === null ? null : SOURCE[contact.email_source]
}
</script>

<template>
    <LeadsLayout>
        <Head title="Contacts" />

        <div class="space-y-4">
            <!-- "412 of 500 imported" with no list is a support ticket, so
                 every rejected row comes back with its line and its reason. -->
            <UAlert
                v-if="props.import"
                :color="props.import.rejected_count ? 'warning' : 'success'"
                variant="subtle"
                icon="i-lucide-upload"
                :title="`${props.import.imported} imported, ${props.import.duplicates} already known, ${props.import.rejected_count} rejected`"
            >
                <template
                    v-if="props.import.rejected.length"
                    #description
                >
                    <ul class="mt-1 space-y-1">
                        <li
                            v-for="row in props.import.rejected"
                            :key="row.line"
                            class="truncate"
                        >
                            <span class="text-dimmed">Line {{ row.line }}</span>
                            <span v-if="row.value"> · {{ row.value }}</span> — {{ row.reason }}
                        </li>
                    </ul>

                    <p
                        v-if="props.import.rejected_count > props.import.rejected.length"
                        class="mt-1 text-dimmed"
                    >
                        and {{ props.import.rejected_count - props.import.rejected.length }} more.
                    </p>

                    <p
                        v-if="props.import.truncated"
                        class="mt-1"
                    >
                        The file was longer than one import can take. Split it and run the rest.
                    </p>
                </template>
            </UAlert>

            <!-- One bar holds everything that narrows the list: the free
                 search, a box per text column, and the two verdicts that are
                 lists rather than free text. -->
            <div class="space-y-3 rounded-lg p-3 ring ring-default">
                <div class="flex flex-wrap items-center gap-3">
                    <UInput
                        v-model="table.search.value"
                        icon="i-lucide-search"
                        placeholder="Search name, role, address, company"
                        class="w-80"
                    />

                    <USelect
                        v-model="status"
                        :items="STATUS_OPTIONS"
                        class="w-52"
                    />

                    <USelect
                        v-model="source"
                        :items="SOURCE_OPTIONS"
                        class="w-56"
                    />

                    <UButton
                        :icon="columnFilters ? 'i-lucide-chevron-up' : 'i-lucide-sliders-horizontal'"
                        color="neutral"
                        variant="ghost"
                        :label="table.activeCount() ? `Columns (${table.activeCount()})` : 'Columns'"
                        @click="columnFilters = !columnFilters"
                    />

                    <UButton
                        v-if="table.activeCount()"
                        icon="i-lucide-x"
                        color="neutral"
                        variant="ghost"
                        label="Clear"
                        @click="table.clear()"
                    />

                    <UButton
                        v-if="filters.company"
                        icon="i-lucide-filter"
                        color="neutral"
                        variant="subtle"
                        label="One company — show all"
                        @click="router.get(contactRoutes.index.url())"
                    />
                </div>

                <div
                    v-if="columnFilters"
                    class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <UFormField
                        v-for="column in FILTERABLE"
                        :key="column.key"
                        :label="column.label"
                        :name="column.key"
                        size="xs"
                    >
                        <UInput
                            v-model="table.filter[column.key]"
                            :placeholder="`Any ${column.label.toLowerCase()}`"
                            class="w-full"
                        />
                    </UFormField>
                </div>

                <p class="text-sm text-muted">
                    <span
                        v-for="(count, key) in counts"
                        :key="key"
                    >
                        {{ count }} {{ STATUS[key as keyof typeof STATUS]?.label.toLowerCase() ?? key }}<span class="text-dimmed"> · </span>
                    </span>
                    <span class="text-dimmed">nothing here was bought.</span>
                </p>
            </div>

            <UTable
                :data="contacts.data"
                :columns="columns"
                :ui="{ td: 'align-top whitespace-normal break-words' }"
            >
                <!-- Headers sort and nothing else; narrowing the list happens
                     in one bar above it. -->
                <template
                    v-for="column in headerSlots"
                    :key="column.key"
                    #[column.slot]
                >
                    <UButton
                        :label="column.label"
                        :trailing-icon="table.sortIcon(column.key)"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        class="-mx-1.5"
                        @click="table.toggleSort(column.key)"
                    />
                </template>

                <template #name-cell="{ row }">
                    <ULink
                        :href="contactRoutes.show.url(row.original.id)"
                        class="font-medium"
                    >{{ row.original.name ?? row.original.email ?? '—' }}</ULink>
                    <ULink
                        v-if="row.original.linkedin_url"
                        :href="row.original.linkedin_url"
                        target="_blank"
                        rel="noopener"
                        class="text-xs"
                    >LinkedIn</ULink>
                </template>

                <template #email-cell="{ row }">
                    <ULink
                        v-if="row.original.email"
                        :href="`mailto:${row.original.email}`"
                    >{{ row.original.email }}</ULink>
                    <span
                        v-else
                        class="text-muted"
                    >No address</span>
                </template>

                <template #email_status-cell="{ row }">
                    <UBadge
                        v-if="verdict(row.original)"
                        :color="verdict(row.original)!.color"
                        variant="subtle"
                        :label="verdict(row.original)!.label"
                        :title="verdict(row.original)!.help"
                    />

                    <!-- Imported rows arrive with no verdict: verifying at
                         import would be a DNS lookup and an SMTP probe per
                         line, with somebody watching a spinner. -->
                    <UBadge
                        v-else-if="row.original.email"
                        color="neutral"
                        variant="outline"
                        label="Not checked"
                    />
                </template>

                <!-- Outreach writes this column itself once sending exists;
                     the select is the user overruling it, and four of the
                     values stop anything going out at all. -->
                <template #status-cell="{ row }">
                    <StatusSelect
                        :status="row.original.status"
                        :options="OUTREACH_STATUSES"
                        :url="contactRoutes.status.url(row.original.id)"
                    />
                </template>

                <template #email_source-cell="{ row }">
                    <span
                        v-if="sourceLabel(row.original)"
                        class="text-sm text-muted"
                    >{{ sourceLabel(row.original) }}</span>

                    <ULink
                        v-if="row.original.source_url"
                        :href="row.original.source_url"
                        target="_blank"
                        rel="noopener"
                        class="block text-xs"
                    >Where we found this</ULink>
                </template>

                <template #company-cell="{ row }">
                    <ULink
                        v-if="row.original.company"
                        :href="contactRoutes.index.url({ query: { company: row.original.company.id } })"
                    >{{ row.original.company.name }}</ULink>
                    <span
                        v-if="row.original.company?.location"
                        class="block text-xs text-dimmed"
                    >{{ row.original.company.location }}</span>
                </template>

                <template #discovered_at-cell="{ row }">
                    <span class="text-sm text-muted">{{ day(row.original.discovered_at) }}</span>
                </template>

                <template #empty>
                    <p class="text-sm text-muted">
                        Nobody yet. Find contacts from the Companies tab — half
                        of small local businesses publish a phone number and no
                        address at all, so an empty answer there is a finding,
                        not a failure.
                    </p>
                </template>
            </UTable>

            <div
                v-if="contacts.meta.last_page > 1"
                class="flex justify-center"
            >
                <UPagination
                    :default-page="contacts.meta.current_page"
                    :items-per-page="contacts.meta.per_page"
                    :total="contacts.meta.total"
                    @update:page="page => table.reload({ page })"
                />
            </div>
        </div>
    </LeadsLayout>
</template>
