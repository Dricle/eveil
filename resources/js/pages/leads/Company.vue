<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import ContactList from '@/components/ContactList.vue'
import LeadsLayout from '@/layouts/LeadsLayout.vue'
import ApproveButton from '@/components/ApproveButton.vue'
import StatusSelect from '@/components/StatusSelect.vue'
import { OUTREACH_STATUSES } from '@/lib/status'
import companyRoutes from '@/routes/companies'
import contactRoutes from '@/routes/contacts'
import type { Activity, CompanySheet } from '@/types'

const props = defineProps<{
    company: CompanySheet
    activity: Activity
}>()

// Only while this company's own search is out: the contacts appear one by one
// as the site is read, and a page that sits still looks like it found nobody.
const poll = usePoll(4000, { only: ['company'] }, { autoStart: props.company.searching })

watch(() => props.company.searching, busy => busy ? poll.start() : poll.stop())

const facts = computed(() => Object.entries(props.company.facts ?? {})
    .filter(([, value]) => value !== null && value !== '')
    .map(([key, value]) => [key.replaceAll('_', ' '), Array.isArray(value) ? value.join(', ') : String(value)]))

function day (value: string | null) {
    return value === null ? 'Never' : new Date(value).toLocaleDateString()
}
</script>

<template>
    <LeadsLayout>
        <Head :title="company.name" />

        <div class="max-w-4xl space-y-4">
            <UButton
                icon="i-lucide-arrow-left"
                color="neutral"
                variant="ghost"
                size="xs"
                label="Companies"
                @click="router.get(companyRoutes.index.url())"
            />

            <div class="space-y-3 rounded-lg p-4 ring ring-default">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-medium">
                            {{ company.name }}
                        </h2>
                        <ULink
                            v-if="company.website"
                            :href="company.website"
                            target="_blank"
                            rel="noopener"
                            class="text-sm"
                        >{{ company.domain ?? company.website }}</ULink>
                        <!-- Not missing data: this business publishes no site,
                             and a directory is where it published an address
                             instead. -->
                        <p
                            v-else
                            class="text-sm text-dimmed"
                        >
                            No site of its own
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Read the fit reason, then decide. This is the page
                             that decision is actually made on. -->
                        <ApproveButton
                            :company="company"
                            size="sm"
                        />

                        <StatusSelect
                            :status="company.status"
                            :options="OUTREACH_STATUSES"
                            :url="companyRoutes.status.url(company.id)"
                        />
                    </div>
                </div>

                <dl class="grid gap-2 text-sm sm:grid-cols-3">
                    <div v-if="company.industry">
                        <dt class="text-dimmed">
                            Industry
                        </dt>
                        <dd>{{ company.industry }}</dd>
                    </div>
                    <div v-if="company.size">
                        <dt class="text-dimmed">
                            Size
                        </dt>
                        <dd>{{ company.size }}</dd>
                    </div>
                    <div v-if="company.location">
                        <dt class="text-dimmed">
                            Location
                        </dt>
                        <dd>{{ company.location }}</dd>
                    </div>
                    <div v-if="company.language">
                        <dt class="text-dimmed">
                            Writes in
                        </dt>
                        <dd>{{ company.language }}</dd>
                    </div>
                    <div>
                        <dt class="text-dimmed">
                            Found
                        </dt>
                        <dd>{{ day(company.discovered_at) }}</dd>
                    </div>
                    <div v-if="company.source_url">
                        <dt class="text-dimmed">
                            Seen on
                        </dt>
                        <dd class="truncate">
                            <ULink
                                :href="company.source_url"
                                target="_blank"
                                rel="noopener"
                            >{{ company.source }}</ULink>
                        </dd>
                    </div>
                </dl>

                <!-- Whatever the directory or the site published beyond the
                     fields above: opening hours, a phone number, a speciality.
                     Kept because a phone number is sometimes the only way in. -->
                <dl
                    v-if="facts.length"
                    class="grid gap-2 border-t border-default pt-3 text-sm sm:grid-cols-2"
                >
                    <div
                        v-for="[label, value] in facts"
                        :key="label"
                    >
                        <dt class="text-dimmed">
                            {{ label }}
                        </dt>
                        <dd>{{ value }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Why it was kept, per segment. This sentence is the opening line
                 of the first mail, so it is worth reading before anything is
                 sent. -->
            <div
                v-if="company.evaluations.length"
                class="space-y-2 rounded-lg p-4 ring ring-default"
            >
                <h3 class="font-medium">
                    Why it was kept
                </h3>

                <div
                    v-for="(evaluation, index) in company.evaluations"
                    :key="index"
                    class="text-sm"
                >
                    <span class="text-dimmed">{{ evaluation.profile ?? 'Deleted profile' }} · {{ evaluation.fit_score }}: </span>
                    {{ evaluation.fit_reason }}
                </div>
            </div>

            <div class="space-y-2 rounded-lg p-4 ring ring-default">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-medium">
                        People here
                    </h3>

                    <UButton
                        v-if="!company.searching"
                        icon="i-lucide-user-search"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        :label="company.contacts.length ? 'Look again' : 'Look for contacts'"
                        @click="router.post(contactRoutes.search.url(), { company: company.id }, { preserveScroll: true })"
                    />
                </div>

                <ContactList
                    :contacts="company.contacts"
                    :searching="company.searching"
                />
            </div>
        </div>
    </LeadsLayout>
</template>
