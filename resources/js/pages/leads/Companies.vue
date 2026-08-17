<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import LeadsLayout from '@/layouts/LeadsLayout.vue'
import companyRoutes from '@/routes/companies'
import contactRoutes from '@/routes/contacts'
import type { Company, Paginated } from '@/types'

// Written out rather than `defineProps<CompanyPage>()`: the compiler cannot
// resolve a type alias imported through the `@/types` barrel, and it fails by
// declaring no props at all — the page renders with everything undefined.
const props = defineProps<{
    companies: Paginated<Company>
    profiles: { id: number, name: string }[]
    filters: { profile: number | null, min_score: number, rejected: boolean }
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

watch([profile, minScore, rejected], () => router.get(companyRoutes.index.url(), {
    profile: profile.value || undefined,
    min_score: minScore.value || undefined,
    rejected: rejected.value ? 1 : undefined
}, { preserveState: true, replace: true, only: ['companies', 'filters', 'total'] }))

function scoreColor (score: number | null) {
    if (score === null) {
        return 'neutral' as const
    }

    return score >= 70 ? 'success' as const : score >= 50 ? 'warning' as const : 'neutral' as const
}
</script>

<template>
    <LeadsLayout>
        <Head title="Companies" />

        <div class="max-w-4xl space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <p class="flex-1 text-sm text-muted">
                    {{ total }} companies kept so far. Each one was found,
                    fetched and read — the reason under the score is what the
                    first email opens with.
                </p>

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
                    v-if="unsearched"
                    icon="i-lucide-users"
                    color="neutral"
                    variant="subtle"
                    :label="`Find contacts (${unsearched})`"
                    @click="router.post(contactRoutes.search.url(), {}, { preserveScroll: true })"
                />
            </div>

            <p
                v-if="!companies.data.length"
                class="text-sm text-muted"
            >
                Nothing here yet. Run a search from Targets, or loosen the
                filters above.
            </p>

            <div
                v-for="company in companies.data"
                :key="company.id"
                class="space-y-2 rounded-lg p-4 ring ring-default"
                :class="company.rejected && 'opacity-60'"
            >
                <div class="flex items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 font-medium">
                            <span class="truncate">{{ company.name }}</span>

                            <UBadge
                                v-if="company.rejected"
                                color="neutral"
                                variant="subtle"
                                label="Rejected"
                            />
                        </p>

                        <p class="truncate text-sm text-muted">
                            <ULink
                                v-if="company.website"
                                :href="company.website"
                                target="_blank"
                                rel="noopener"
                            >{{ company.domain }}</ULink>
                            <span v-else>{{ company.domain }}</span>

                            <span v-if="company.location"> · {{ company.location }}</span>
                            <span v-if="company.industry"> · {{ company.industry }}</span>
                            <span v-if="company.size"> · {{ company.size }}</span>
                        </p>
                    </div>

                    <ULink
                        v-if="company.contacts_count"
                        :href="contactRoutes.index.url({ query: { company: company.id } })"
                        class="shrink-0 text-sm text-muted"
                    >
                        {{ company.contacts_count }} contacts
                    </ULink>

                    <UButton
                        v-else-if="!company.rejected"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        :icon="company.contacts_status === 'queued' ? 'i-lucide-loader' : 'i-lucide-user-search'"
                        :ui="{ leadingIcon: company.contacts_status === 'queued' ? 'animate-spin' : '' }"
                        :label="{ queued: 'Looking…', done: 'Nobody found', failed: 'Could not read' }[company.contacts_status ?? ''] ?? 'Find contacts'"
                        :disabled="company.contacts_status === 'queued'"
                        @click="router.post(contactRoutes.search.url(), { company: company.id }, { preserveScroll: true })"
                    />

                    <UBadge
                        :color="scoreColor(company.fit_score)"
                        variant="subtle"
                        :label="`${company.fit_score ?? '—'}/100`"
                    />

                    <UButton
                        :color="company.rejected ? 'neutral' : 'error'"
                        variant="ghost"
                        size="xs"
                        :icon="company.rejected ? 'i-lucide-undo-2' : 'i-lucide-x'"
                        :aria-label="company.rejected ? 'Put this company back' : 'Reject this company'"
                        @click="company.rejected
                            ? router.delete(companyRoutes.restore.url(company.id), { preserveScroll: true })
                            : router.post(companyRoutes.reject.url(company.id), {}, { preserveScroll: true })"
                    />
                </div>

                <div
                    v-for="evaluation in company.evaluations"
                    :key="evaluation.profile ?? 'gone'"
                    class="text-sm"
                >
                    <p class="text-muted">
                        <span class="text-dimmed">{{ evaluation.profile ?? 'Deleted profile' }} · {{ evaluation.fit_score }}</span>
                        — {{ evaluation.fit_reason }}
                    </p>
                </div>
            </div>

            <div
                v-if="companies.meta.last_page > 1"
                class="flex justify-center"
            >
                <UPagination
                    :default-page="companies.meta.current_page"
                    :items-per-page="companies.meta.per_page"
                    :total="companies.meta.total"
                    @update:page="page => router.get(companyRoutes.index.url(), { ...filters, page }, { preserveState: true })"
                />
            </div>
        </div>
    </LeadsLayout>
</template>
