<script setup lang="ts">
import { Form, Head, router, usePoll } from '@inertiajs/vue3'
import type { TableColumn } from '@nuxt/ui'
import { computed, ref, watch } from 'vue'
import LeadsLayout from '@/layouts/LeadsLayout.vue'
import ApproveButton from '@/components/ApproveButton.vue'
import SearchingBanner from '@/components/SearchingBanner.vue'
import StatusSelect from '@/components/StatusSelect.vue'
import { OUTREACH_STATUSES } from '@/lib/status'
import { useTableQuery } from '@/lib/table'
import companyRoutes from '@/routes/companies'
import contactRoutes from '@/routes/contacts'
import type { Activity, Company, Paginated } from '@/types'

// Written out rather than `defineProps<CompanyPage>()`: the compiler cannot
// resolve a type alias imported through the `@/types` barrel, and it fails by
// declaring no props at all, so the page renders with everything undefined.
const props = defineProps<{
    companies: Paginated<Company>
    profiles: { id: number, name: string }[]
    filters: {
        profile: number | null
        min_score: number
        excluded: boolean
        unapproved: boolean
        search: string | null
        filter: Record<string, string>
        sort: string | null
        direction: string | null
    }
    total: number
    unsearched: number
    unapproved: number
    activity: Activity
}>()

// Anything in flight, not just a contact search: a discovery run fills this
// list for minutes, and a page that sits still meanwhile reads as an empty
// market rather than as one still being searched.
const searching = computed(() => props.activity.searching)

const poll = usePoll(4000, { only: ['companies', 'activity', 'total', 'unsearched', 'unapproved'] }, { autoStart: searching.value })

watch(searching, busy => busy ? poll.start() : poll.stop())

const profile = ref(props.filters.profile ?? 0)
const minScore = ref(props.filters.min_score ?? 0)
const excluded = ref(props.filters.excluded)
const awaiting = ref(props.filters.unapproved)

const table = useTableQuery(
    companyRoutes.index.url(),
    props.filters,
    ['companies', 'filters', 'total'],
    () => ({
        profile: profile.value || undefined,
        min_score: minScore.value || undefined,
        excluded: excluded.value ? 1 : undefined,
        unapproved: awaiting.value ? 1 : undefined
    })
)

watch([profile, minScore, excluded, awaiting], () => table.reload())

const PROFILE_OPTIONS = computed(() => [
    { label: 'Every profile', value: 0 },
    ...props.profiles.map(item => ({ label: item.name, value: item.id }))
])

// A company arriving by paste still needs a profile to score it against: fit
// score lives on the (company, profile) pair, never on the company alone. No
// "every profile" option here, unlike the filter above.
const addingLinks = ref(false)
const linkProfile = ref<number | undefined>(props.profiles[0]?.id)
const links = ref('')
const PROFILE_SELECT_OPTIONS = computed(() => props.profiles.map(item => ({ label: item.name, value: item.id })))

const SCORE_OPTIONS = [
    { label: 'Any score', value: 0 },
    { label: '50 and above', value: 50 },
    { label: '70 and above', value: 70 },
    { label: '85 and above', value: 85 }
]

// The key doubles as the sort key the server accepts and as the slot name, so
// a column sorts, filters and renders under one name on both sides.
// `width` is a min/max pair per column, because the qualifier writes a whole
// sentence into fields a table would like to keep to a word. "Size" comes back
// as "commune of medium size running several municipal nurseries". Without a
// floor those columns squeeze to one word per line; without a ceiling they take
// the room the fit reason needs, which is the one column here worth reading.
const COLUMNS = [
    { key: 'name', label: 'Company', sortable: true, filterable: true, width: 'min-w-32 max-w-40' },
    { key: 'status', label: 'Status', sortable: true, filterable: false, width: 'min-w-32' },
    { key: 'approval', label: 'Approved', sortable: false, filterable: false, width: 'min-w-28' },
    { key: 'domain', label: 'Domain', sortable: true, filterable: true, width: 'min-w-28 max-w-32' },
    { key: 'industry', label: 'Industry', sortable: true, filterable: true, width: 'min-w-32 max-w-40' },
    { key: 'size', label: 'Size', sortable: true, filterable: true, width: 'min-w-32 max-w-40' },
    { key: 'location', label: 'Location', sortable: true, filterable: true, width: 'min-w-24 max-w-32' },
    { key: 'fit_score', label: 'Fit', sortable: true, filterable: false, width: '' },
    { key: 'reason', label: 'Why it fits', sortable: false, filterable: false, width: 'min-w-48' },
    { key: 'contacts_count', label: 'Contacts', sortable: true, filterable: false, width: '' },
    { key: 'discovered_at', label: 'Found', sortable: true, filterable: false, width: '' },
    { key: 'details', label: '', sortable: false, filterable: false, width: '' }
]

const columns: TableColumn<Company>[] = COLUMNS.map(column => ({
    accessorKey: column.key,
    header: column.label,
    meta: { class: { td: column.width, th: column.width } }
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
// "Nobody" and "unreadable" are different findings about a company, and only
// one of them is worth looking at again.
function searchLabel (company: Company) {
    const labels: Record<string, string> = { done: 'Nobody', failed: 'Unreadable' }

    return labels[company.contacts_status ?? ''] ?? 'Not yet'
}
</script>

<template>
    <LeadsLayout>
        <Head title="Companies" />

        <div class="space-y-4">
            <SearchingBanner :activity="activity" />

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
                        v-model="excluded"
                        label="Show set aside"
                    />

                    <!-- The working queue. Nothing moves until these are
                         decided, so it is worth being one switch away. -->
                    <USwitch
                        v-model="awaiting"
                        :label="unapproved ? `Awaiting approval (${unapproved})` : 'Awaiting approval'"
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
                        v-if="companies.data.some(company => !company.approved)"
                        icon="i-lucide-thumbs-up"
                        color="primary"
                        variant="subtle"
                        :label="`Approve these ${companies.data.filter(company => !company.approved).length}`"
                        @click="router.put(
                            companyRoutes.approval.url(),
                            { companies: companies.data.filter(company => !company.approved).map(company => company.id), approved: true },
                            { preserveScroll: true }
                        )"
                    />

                    <UButton
                        v-if="unsearched"
                        icon="i-lucide-users"
                        color="neutral"
                        variant="subtle"
                        :label="`Find contacts (${unsearched})`"
                        @click="router.post(contactRoutes.search.url(), {}, { preserveScroll: true })"
                    />

                    <!-- A lead somebody already had. One way companies arrive,
                         not a place you go, so it is a button here rather than
                         a section of its own -- same reasoning as importing a
                         list of contacts. -->
                    <UButton
                        icon="i-lucide-link"
                        color="neutral"
                        variant="subtle"
                        label="Add links"
                        @click="addingLinks = true"
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
                    <ULink
                        :href="companyRoutes.show.url(row.original.id)"
                        class="font-medium"
                    >{{ row.original.name }}</ULink>
                </template>

                <template
                    v-for="key in ['industry', 'size'] as const"
                    :key="key"
                    #[`${key}-cell`]="{ row }"
                >
                    <p
                        class="line-clamp-3 text-sm"
                        :title="row.original[key] ?? undefined"
                    >
                        {{ row.original[key] }}
                    </p>
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
                        :label="`${row.original.fit_score ?? 'n/a'}`"
                    />
                </template>

                <!-- The reason is not a note to ourselves: it is the line the
                     first email opens with, so it stays on the row. -->
                <!-- The whole sentence, wrapped: it is the opening line of
                     the email, so a clamped version is the one thing on this
                     row nobody can judge. -->
                <template #reason-cell="{ row }">
                    <p
                        v-if="best(row.original)"
                        class="min-w-64 text-sm text-muted"
                    >
                        <span class="text-dimmed">{{ best(row.original)?.profile ?? 'Deleted profile' }} · </span>
                        {{ best(row.original)?.fit_reason }}
                    </p>
                </template>

                <!-- No per-row button to go looking: the search is dispatched
                     the moment a company is kept, because forty companies is
                     forty clicks nobody makes. This says where that search got
                     to. -->
                <template #contacts_count-cell="{ row }">
                    <ULink
                        v-if="row.original.contacts_count"
                        :href="contactRoutes.index.url({ query: { company: row.original.id } })"
                    >{{ row.original.contacts_count }}</ULink>

                    <span
                        v-else-if="row.original.contacts_status === 'queued'"
                        class="flex items-center gap-1 text-sm text-muted"
                    >
                        <UIcon
                            name="i-lucide-search"
                            class="animate-sweep size-4 text-primary"
                        />
                        Looking
                    </span>

                    <span
                        v-else
                        class="text-sm text-dimmed"
                    >{{ searchLabel(row.original) }}</span>
                </template>

                <template #discovered_at-cell="{ row }">
                    <span class="text-sm text-muted">{{ day(row.original.discovered_at) }}</span>
                </template>

                <template #details-cell="{ row }">
                    <UButton
                        icon="i-lucide-arrow-right"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        label="Details"
                        @click="router.get(companyRoutes.show.url(row.original.id))"
                    />
                </template>

                <!-- A company somebody already sells to is the one row that
                     must never be written to, and no score can know it. -->
                <template #status-cell="{ row }">
                    <StatusSelect
                        :status="row.original.status"
                        :options="OUTREACH_STATUSES"
                        :url="companyRoutes.status.url(row.original.id)"
                    />
                </template>

                <!-- The last human decision before mail leaves. Saying yes
                     also starts the search for people at that company. -->
                <template #approval-cell="{ row }">
                    <ApproveButton :company="row.original" />
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

        <UModal
            v-model:open="addingLinks"
            title="Add links"
            description="A company site, a directory page, anything you already found by hand. Each one is read and routed the same way a search result is."
            :ui="{ content: 'max-w-xl' }"
        >
            <template #body>
                <Form
                    v-slot="{ errors, processing }"
                    v-bind="companyRoutes.links.store.form()"
                    class="space-y-4"
                    @success="addingLinks = false; links = ''"
                >
                    <UFormField
                        label="Score against"
                        name="target_profile"
                        :error="errors.target_profile"
                    >
                        <USelect
                            v-model="linkProfile"
                            name="target_profile"
                            :items="PROFILE_SELECT_OPTIONS"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        label="Links"
                        name="links"
                        :error="errors.links"
                        help="One per line, up to 50."
                    >
                        <UTextarea
                            v-model="links"
                            name="links"
                            placeholder="https://example.com&#10;https://directory.example.com/plumbers/namur"
                            :rows="6"
                            class="w-full"
                        />
                    </UFormField>

                    <div class="flex justify-end gap-2">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            label="Cancel"
                            :disabled="processing"
                            @click="addingLinks = false"
                        />
                        <UButton
                            type="submit"
                            label="Add links"
                            :loading="processing"
                        />
                    </div>
                </Form>
            </template>
        </UModal>
    </LeadsLayout>
</template>
