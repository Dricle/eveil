<script setup lang="ts">
import { Form, Head, router, usePoll } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import LeadsLayout from '@/layouts/LeadsLayout.vue'
import SearchingBanner from '@/components/SearchingBanner.vue'
import { useTableQuery } from '@/lib/table'
import companyRoutes from '@/routes/companies'
import contactRoutes from '@/routes/contacts'
import type { Activity, Company, Paginated } from '@/types'

type View = 'all' | 'awaiting' | 'approved' | 'no_contact' | 'set_aside'

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
        view: View | null
        search: string | null
        filter: Record<string, string>
        sort: string | null
        direction: string | null
    }
    total: number
    unsearched: number
    unapproved: number
    counts: { all: number, awaiting: number, approved: number, no_contact: number, set_aside: number }
    activity: Activity
}>()

// Anything in flight, not just a contact search: a discovery run fills this
// list for minutes, and a page that sits still meanwhile reads as an empty
// market rather than as one still being searched.
const searching = computed(() => props.activity.searching)

const poll = usePoll(4000, { only: ['companies', 'activity', 'total', 'unsearched', 'unapproved', 'counts'] }, { autoStart: searching.value })

watch(searching, busy => busy ? poll.start() : poll.stop())

const profile = ref(props.filters.profile ?? 0)
const minScore = ref(props.filters.min_score ?? 0)
const view = ref<View>(props.filters.view ?? 'all')

const table = useTableQuery(
    companyRoutes.index.url(),
    props.filters,
    ['companies', 'filters', 'total', 'unsearched', 'unapproved', 'counts'],
    () => ({
        profile: profile.value || undefined,
        min_score: minScore.value || undefined,
        view: view.value === 'all' ? undefined : view.value
    })
)

watch([profile, minScore, view], () => table.reload())

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

// The status pills above the list. Backed by `view` above: each one is a
// whole replacement for "which companies", never another switch stacked on
// top of another.
const PILLS: { key: View, label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'awaiting', label: 'Awaiting approval' },
    { key: 'approved', label: 'Approved' },
    { key: 'no_contact', label: 'No contact yet' },
    { key: 'set_aside', label: 'Set aside' }
]

// One column filter box per column, same set the old table exposed.
const FILTERABLE = [
    { key: 'name', label: 'Company' },
    { key: 'domain', label: 'Domain' },
    { key: 'industry', label: 'Industry' },
    { key: 'size', label: 'Size' },
    { key: 'location', label: 'Location' }
]

// Open when the person arrived with a column filter already applied, so a
// narrowed list never looks unfiltered.
const columnFilters = ref(Object.keys(props.filters.filter ?? {}).length > 0)

// The literal class names Tailwind needs to see somewhere in source: a
// `text-${color}` interpolation would never be picked up by the scanner.
const SCORE_CLASSES: Record<'success' | 'warning' | 'neutral', { text: string, bar: string }> = {
    success: { text: 'text-success', bar: 'bg-success' },
    warning: { text: 'text-warning', bar: 'bg-warning' },
    neutral: { text: 'text-muted', bar: 'bg-muted' }
}

function scoreColor (score: number | null) {
    if (score === null) {
        return 'neutral' as const
    }

    return score >= 70 ? 'success' as const : score >= 50 ? 'warning' as const : 'neutral' as const
}

function best (company: Company) {
    return company.evaluations[0] ?? null
}

function metaParts (company: Company): string[] {
    return [company.industry, company.size, company.location].filter((value): value is string => !!value)
}

function day (value: string) {
    return new Date(value).toLocaleDateString()
}

/** What the contact line under a company reads, if anything. */
function contactState (company: Company): 'found' | 'looking' | 'none' | 'unreadable' | null {
    if (company.contacts_count > 0) {
        return 'found'
    }

    if (company.contacts_status === 'queued') {
        return 'looking'
    }

    if (company.contacts_status === 'done') {
        return 'none'
    }

    if (company.contacts_status === 'failed') {
        return 'unreadable'
    }

    // Never searched: nothing to report yet, so nothing is said.
    return null
}

// Selection lives in the browser only, never in the URL: it is a scratch pad
// for the bulk toolbar, and it is cleared the moment the list underneath it
// changes shape.
const selected = ref<number[]>([])

watch(() => props.companies.data, clearSelection)

function toggle (id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter(existing => existing !== id)
        : [...selected.value, id]
}

function clearSelection () {
    selected.value = []
}

function bulkApprove () {
    router.put(
        companyRoutes.approval.url(),
        { companies: selected.value, approved: true },
        { preserveScroll: true, onSuccess: clearSelection }
    )
}

function bulkFindContacts () {
    router.post(
        contactRoutes.search.url(),
        { companies: selected.value },
        { preserveScroll: true, onSuccess: clearSelection }
    )
}

function bulkSetAside () {
    router.put(
        companyRoutes.status.bulk.url(),
        { companies: selected.value, status: 'rejected' },
        { preserveScroll: true, onSuccess: clearSelection }
    )
}

function approve (company: Company) {
    router.put(
        companyRoutes.approval.url(),
        { companies: [company.id], approved: true },
        { preserveScroll: true, preserveState: true }
    )
}

function setAside (company: Company) {
    router.put(companyRoutes.status.url(company.id), { status: 'rejected' }, { preserveScroll: true, preserveState: true })
}

function putBack (company: Company) {
    router.put(companyRoutes.status.url(company.id), { status: 'new' }, { preserveScroll: true, preserveState: true })
}

function findContacts (company: Company) {
    router.post(contactRoutes.search.url(), { company: company.id }, { preserveScroll: true })
}
</script>

<template>
    <LeadsLayout>
        <Head title="Companies" />

        <div class="space-y-4">
            <SearchingBanner :activity="activity" />

            <!-- One bar holds everything that narrows the list: the free
                 search, the score/profile selects, and the column boxes. -->
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

                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        <UButton
                            v-if="unsearched"
                            icon="i-lucide-users"
                            color="neutral"
                            variant="subtle"
                            :label="`Find contacts (${unsearched})`"
                            @click="router.post(contactRoutes.search.url(), {}, { preserveScroll: true })"
                        />

                        <!-- A lead somebody already had. One way companies
                             arrive, not a place you go -- same reasoning as
                             importing a list of contacts. -->
                        <UButton
                            icon="i-lucide-link"
                            color="neutral"
                            variant="subtle"
                            label="Add links"
                            @click="addingLinks = true"
                        />
                    </div>
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

            <!-- The status pills. One is always active, `view` above holds
                 which. -->
            <div class="flex flex-wrap items-center gap-2">
                <UButton
                    v-for="pill in PILLS"
                    :key="pill.key"
                    :label="`${pill.label} ${counts[pill.key]}`"
                    size="sm"
                    :color="view === pill.key ? 'primary' : 'neutral'"
                    :variant="view === pill.key ? 'subtle' : 'outline'"
                    class="rounded-full"
                    @click="view = pill.key"
                />
                <p class="ml-auto text-xs text-dimmed">
                    Sorted by fit
                </p>
            </div>

            <!-- The bulk toolbar, only once something is picked: acting on a
                 selection is the whole point of the checkboxes below. -->
            <div
                v-if="selected.length"
                class="flex flex-wrap items-center gap-3 rounded-lg bg-primary/10 px-3.5 py-2.5 ring ring-primary/25"
            >
                <span class="text-sm font-medium text-highlighted">{{ selected.length }} selected</span>
                <div class="flex flex-wrap gap-1.5">
                    <UButton
                        label="Approve"
                        size="xs"
                        @click="bulkApprove"
                    />
                    <UButton
                        label="Find contacts"
                        size="xs"
                        color="neutral"
                        variant="outline"
                        @click="bulkFindContacts"
                    />
                    <UButton
                        label="Set aside"
                        size="xs"
                        color="neutral"
                        variant="ghost"
                        @click="bulkSetAside"
                    />
                </div>
                <UButton
                    label="Clear"
                    size="xs"
                    color="neutral"
                    variant="link"
                    class="ml-auto"
                    @click="clearSelection"
                />
            </div>

            <div class="grid gap-2">
                <div
                    v-for="company in companies.data"
                    :key="company.id"
                    class="grid grid-cols-[auto_1fr] items-start gap-3 rounded-lg p-3.5 ring transition-colors"
                    :class="selected.includes(company.id) ? 'bg-elevated ring-primary/40' : 'bg-elevated/60 ring-default'"
                >
                    <UCheckbox
                        :model-value="selected.includes(company.id)"
                        class="mt-0.5"
                        @update:model-value="toggle(company.id)"
                    />

                    <div class="min-w-0 space-y-1.5">
                        <div class="flex flex-wrap items-baseline gap-2">
                            <ULink
                                :href="companyRoutes.show.url(company.id)"
                                class="font-semibold text-highlighted"
                            >{{ company.name }}</ULink>

                            <ULink
                                v-if="company.website"
                                :href="company.website"
                                target="_blank"
                                rel="noopener"
                                class="min-w-0 truncate font-mono text-xs text-dimmed"
                            >{{ company.domain }}</ULink>
                            <span
                                v-else-if="company.domain"
                                class="min-w-0 truncate font-mono text-xs text-dimmed"
                            >{{ company.domain }}</span>

                            <span
                                v-if="company.fit_score !== null"
                                class="ml-auto flex shrink-0 items-center gap-1.5"
                            >
                                <span
                                    class="font-mono text-xs"
                                    :class="SCORE_CLASSES[scoreColor(company.fit_score)].text"
                                >{{ company.fit_score }}</span>
                                <span class="block h-[3px] w-8 overflow-hidden rounded-full bg-accented">
                                    <span
                                        class="block h-full"
                                        :class="SCORE_CLASSES[scoreColor(company.fit_score)].bar"
                                        :style="{ width: `${company.fit_score}%` }"
                                    />
                                </span>
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
                            <template
                                v-for="(part, index) in metaParts(company)"
                                :key="index"
                            >
                                <span
                                    v-if="index > 0"
                                    class="opacity-40"
                                >·</span>
                                <span>{{ part }}</span>
                            </template>
                            <span
                                v-if="metaParts(company).length"
                                class="opacity-40"
                            >·</span>
                            <span class="text-dimmed">Found {{ day(company.discovered_at) }}</span>
                        </div>

                        <!-- The reason is not a note to ourselves: it is the
                             line the first email opens with. -->
                        <p
                            v-if="best(company)"
                            class="max-w-[88ch] text-sm text-toned"
                        >
                            {{ best(company)?.fit_reason }}
                        </p>

                        <div class="flex flex-wrap items-center gap-2 pt-0.5">
                            <UBadge
                                v-if="company.excluded"
                                color="neutral"
                                variant="subtle"
                                icon="i-lucide-minus"
                                label="Set aside"
                            />
                            <UBadge
                                v-else-if="company.approved"
                                color="success"
                                variant="subtle"
                                icon="i-lucide-check"
                                label="Approved"
                            />
                            <UBadge
                                v-else
                                color="warning"
                                variant="subtle"
                                icon="i-lucide-clock"
                                label="Awaiting approval"
                            />

                            <UBadge
                                v-if="best(company)"
                                color="neutral"
                                variant="outline"
                                :label="best(company)?.profile ?? 'Deleted profile'"
                                class="max-w-64 truncate"
                            />

                            <!-- No per-row button to go looking: the search is
                                 dispatched the moment a company is kept,
                                 because forty companies is forty clicks
                                 nobody makes. This says where that search
                                 got to. -->
                            <span
                                v-if="contactState(company) === 'looking'"
                                class="flex items-center gap-1 text-xs text-muted"
                            >
                                <UIcon
                                    name="i-lucide-search"
                                    class="animate-sweep size-3.5 text-primary"
                                />
                                Looking
                            </span>
                            <ULink
                                v-else-if="contactState(company) === 'found'"
                                :href="contactRoutes.index.url({ query: { company: company.id } })"
                                class="flex items-center gap-1 text-xs"
                            >
                                <UIcon
                                    name="i-lucide-users"
                                    class="size-3.5"
                                />
                                {{ company.contacts_count }} contact{{ company.contacts_count === 1 ? '' : 's' }}
                            </ULink>
                            <span
                                v-else-if="contactState(company) === 'none'"
                                class="text-xs text-warning"
                            >No contact found yet</span>
                            <span
                                v-else-if="contactState(company) === 'unreadable'"
                                class="text-xs text-dimmed"
                            >Unreadable</span>

                            <div class="ml-auto flex flex-wrap gap-1.5">
                                <UButton
                                    v-if="company.excluded"
                                    label="Put back"
                                    size="xs"
                                    color="neutral"
                                    variant="outline"
                                    @click="putBack(company)"
                                />
                                <template v-else-if="!company.approved">
                                    <UButton
                                        label="Approve"
                                        size="xs"
                                        @click="approve(company)"
                                    />
                                    <UButton
                                        label="Set aside"
                                        size="xs"
                                        color="neutral"
                                        variant="ghost"
                                        @click="setAside(company)"
                                    />
                                </template>
                                <UButton
                                    v-else-if="company.contacts_count > 0"
                                    label="Details"
                                    trailing-icon="i-lucide-arrow-right"
                                    size="xs"
                                    color="neutral"
                                    variant="ghost"
                                    @click="router.get(companyRoutes.show.url(company.id))"
                                />
                                <UButton
                                    v-else-if="contactState(company) !== 'looking'"
                                    label="Find contacts"
                                    size="xs"
                                    color="neutral"
                                    variant="outline"
                                    @click="findContacts(company)"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <p
                    v-if="!companies.data.length"
                    class="text-sm text-muted"
                >
                    Nothing here. Run a search from Targets, or loosen the
                    filters above.
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="text-xs text-dimmed">Showing {{ companies.data.length }} of {{ companies.meta.total }} companies</span>

                <UPagination
                    v-if="companies.meta.last_page > 1"
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
