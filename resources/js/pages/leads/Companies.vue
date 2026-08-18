<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3'
import type { TableColumn } from '@nuxt/ui'
import { computed, ref, watch } from 'vue'
import LeadsLayout from '@/layouts/LeadsLayout.vue'
import { useTableQuery } from '@/lib/table'
import companyRoutes from '@/routes/companies'
import contactRoutes from '@/routes/contacts'
import type { Company, Paginated } from '@/types'

// Written out rather than `defineProps<CompanyPage>()`: the compiler cannot
// resolve a type alias imported through the `@/types` barrel, and it fails by
// declaring no props at all — the page renders with everything undefined.
const props = defineProps<{
    companies: Paginated<Company>
    profiles: { id: number, name: string }[]
    filters: {
        profile: number | null
        min_score: number
        rejected: boolean
        search: string | null
        filter: Record<string, string>
        sort: string | null
        direction: string | null
    }
    total: number
    unsearched: number
}>()

const searching = computed(() => props.companies.data.some(company => company.contacts_status === 'queued'))

// Only while a contact search is out. Companies do not change on their own.
const poll = usePoll(4000, { only: ['companies'] }, { autoStart: searching.value })

watch(searching, busy => busy ? poll.start() : poll.stop())

const profile = ref(props.filters.profile ?? 0)
const minScore = ref(props.filters.min_score ?? 0)
const rejected = ref(props.filters.rejected)

const table = useTableQuery(
    companyRoutes.index.url(),
    props.filters,
    ['companies', 'filters', 'total'],
    () => ({
        profile: profile.value || undefined,
        min_score: minScore.value || undefined,
        rejected: rejected.value ? 1 : undefined
    })
)

watch([profile, minScore, rejected], () => table.reload())

const PROFILE_OPTIONS = computed(() => [
    { label: 'Every profile', value: 0 },
    ...props.profiles.map(item => ({ label: item.name, value: item.id }))
])

const SCORE_OPTIONS = [
    { label: 'Any score', value: 0 },
    { label: '50 and above', value: 50 },
    { label: '70 and above', value: 70 },
    { label: '85 and above', value: 85 }
]

// The key doubles as the sort key the server accepts and as the slot name, so
// a column sorts, filters and renders under one name on both sides.
const COLUMNS = [
    { key: 'name', label: 'Company', sortable: true, filterable: true },
    { key: 'domain', label: 'Domain', sortable: true, filterable: true },
    { key: 'industry', label: 'Industry', sortable: true, filterable: true },
    { key: 'size', label: 'Size', sortable: true, filterable: true },
    { key: 'location', label: 'Location', sortable: true, filterable: true },
    { key: 'fit_score', label: 'Fit', sortable: true, filterable: false },
    { key: 'reason', label: 'Why it fits', sortable: false, filterable: false },
    { key: 'contacts_count', label: 'Contacts', sortable: true, filterable: false },
    { key: 'discovered_at', label: 'Found', sortable: true, filterable: false },
    { key: 'actions', label: '', sortable: false, filterable: false }
]

const columns: TableColumn<Company>[] = COLUMNS.map(column => ({
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

function scoreColor (score: number | null) {
    if (score === null) {
        return 'neutral' as const
    }

    return score >= 70 ? 'success' as const : score >= 50 ? 'warning' as const : 'neutral' as const
}

function best (company: Company) {
    return company.evaluations[0] ?? null
}

function day (value: string) {
    return new Date(value).toLocaleDateString()
}

// `row.original` reaches a slot untyped, so anything that indexes a lookup map
// does it behind a typed parameter rather than in the template.
function searchLabel (company: Company) {
    const labels: Record<string, string> = { queued: 'Looking…', done: 'Nobody', failed: 'Unreadable' }

    return labels[company.contacts_status ?? ''] ?? 'Find'
}
</script>

<template>
    <LeadsLayout>
        <Head title="Companies" />

        <div class="space-y-4">
            <!-- One bar holds everything that narrows the list: the free
                 search, a box per column, and the filters that are not columns
                 at all. -->
            <div class="space-y-3 rounded-lg p-3 ring ring-default">
                <div class="flex flex-wrap items-center gap-3">
                    <UInput
                        v-model="table.search.value"
                        icon="i-lucide-search"
                        placeholder="Search name, domain, industry, size, location"
                        class="w-96"
                    />

                    <USelect
                        v-model="profile"
                        :items="PROFILE_OPTIONS"
                        class="w-48"
                    />

                    <USelect
                        v-model="minScore"
                        :items="SCORE_OPTIONS"
                        class="w-40"
                    />

                    <USwitch
                        v-model="rejected"
                        label="Show rejected"
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
                        v-if="unsearched"
                        icon="i-lucide-users"
                        color="neutral"
                        variant="subtle"
                        :label="`Find contacts (${unsearched})`"
                        @click="router.post(contactRoutes.search.url(), {}, { preserveScroll: true })"
                    />

                    <p class="flex-1 text-right text-sm text-muted">
                        {{ companies.meta.total }} of {{ total }} companies
                    </p>
                </div>

                <div
                    v-if="columnFilters"
                    class="grid gap-3 sm:grid-cols-3 lg:grid-cols-5"
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
            </div>

            <UTable
                :data="companies.data"
                :columns="columns"
                :ui="{ td: 'align-top', tr: 'data-[rejected=true]:opacity-60' }"
            >
                <!-- Headers sort and nothing else; narrowing the list happens
                     in one bar above it. -->
                <template
                    v-for="column in headerSlots"
                    :key="column.key"
                    #[column.slot]
                >
                    <UButton
                        v-if="column.sortable"
                        :label="column.label"
                        :trailing-icon="table.sortIcon(column.key)"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        class="-mx-1.5"
                        @click="table.toggleSort(column.key)"
                    />
                    <span
                        v-else
                        class="text-xs text-muted"
                    >{{ column.label }}</span>
                </template>

                <template #name-cell="{ row }">
                    <p class="font-medium">
                        {{ row.original.name }}
                    </p>
                    <UBadge
                        v-if="row.original.rejected"
                        color="neutral"
                        variant="subtle"
                        size="sm"
                        label="Rejected"
                    />
                </template>

                <template #domain-cell="{ row }">
                    <ULink
                        v-if="row.original.website"
                        :href="row.original.website"
                        target="_blank"
                        rel="noopener"
                    >{{ row.original.domain }}</ULink>
                    <!-- Not missing data: this business publishes no site, and
                         a directory is where it published an address instead. -->
                    <span
                        v-else-if="!row.original.domain"
                        class="text-dimmed"
                    >No site</span>
                    <span v-else>{{ row.original.domain }}</span>
                </template>

                <template #fit_score-cell="{ row }">
                    <UBadge
                        :color="scoreColor(row.original.fit_score)"
                        variant="subtle"
                        :label="`${row.original.fit_score ?? '—'}`"
                    />
                </template>

                <!-- The reason is not a note to ourselves: it is the line the
                     first email opens with, so it stays on the row. -->
                <template #reason-cell="{ row }">
                    <p
                        v-if="best(row.original)"
                        class="max-w-md text-sm text-muted"
                        :title="best(row.original)?.fit_reason ?? undefined"
                    >
                        <span class="text-dimmed">{{ best(row.original)?.profile ?? 'Deleted profile' }} · </span>
                        <span class="line-clamp-2">{{ best(row.original)?.fit_reason }}</span>
                    </p>
                </template>

                <template #contacts_count-cell="{ row }">
                    <ULink
                        v-if="row.original.contacts_count"
                        :href="contactRoutes.index.url({ query: { company: row.original.id } })"
                    >{{ row.original.contacts_count }}</ULink>

                    <UButton
                        v-else-if="!row.original.rejected"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        :icon="row.original.contacts_status === 'queued' ? 'i-lucide-loader' : 'i-lucide-user-search'"
                        :ui="{ leadingIcon: row.original.contacts_status === 'queued' ? 'animate-spin' : '' }"
                        :label="searchLabel(row.original)"
                        :disabled="row.original.contacts_status === 'queued'"
                        @click="router.post(contactRoutes.search.url(), { company: row.original.id }, { preserveScroll: true })"
                    />
                </template>

                <template #discovered_at-cell="{ row }">
                    <span class="text-sm text-muted">{{ day(row.original.discovered_at) }}</span>
                </template>

                <template #actions-cell="{ row }">
                    <UButton
                        :color="row.original.rejected ? 'neutral' : 'error'"
                        variant="ghost"
                        size="xs"
                        :icon="row.original.rejected ? 'i-lucide-undo-2' : 'i-lucide-x'"
                        :aria-label="row.original.rejected ? 'Put this company back' : 'Reject this company'"
                        @click="row.original.rejected
                            ? router.delete(companyRoutes.restore.url(row.original.id), { preserveScroll: true })
                            : router.post(companyRoutes.reject.url(row.original.id), {}, { preserveScroll: true })"
                    />
                </template>

                <template #empty>
                    <p class="text-sm text-muted">
                        Nothing here. Run a search from Targets, or loosen the
                        filters above.
                    </p>
                </template>
            </UTable>

            <div
                v-if="companies.meta.last_page > 1"
                class="flex justify-center"
            >
                <UPagination
                    :default-page="companies.meta.current_page"
                    :items-per-page="companies.meta.per_page"
                    :total="companies.meta.total"
                    @update:page="page => table.reload({ page })"
                />
            </div>
        </div>
    </LeadsLayout>
</template>
