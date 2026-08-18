<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import LeadsLayout from '@/layouts/LeadsLayout.vue'
import contactRoutes from '@/routes/contacts'
import type { Contact, Paginated } from '@/types'

// Inline, not a type alias: see the note in Companies.vue — an alias imported
// through the barrel silently declares no props at all.
const props = defineProps<{
    contacts: Paginated<Contact>
    filters: { email_status: string | null, company: number | null }
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

watch(status, () => router.get(contactRoutes.index.url(), {
    email_status: status.value === 'all' ? undefined : status.value,
    company: props.filters.company ?? undefined
}, { preserveState: true, replace: true, only: ['contacts', 'filters'] }))

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

const FILTERS = [
    { label: 'Everything sendable', value: 'all' },
    { label: 'Verified', value: 'valid' },
    { label: 'Unverified', value: 'unknown' },
    { label: 'Catch-all', value: 'risky' },
    { label: 'Invalid', value: 'invalid' }
]
</script>

<template>
    <LeadsLayout>
        <Head title="Contacts" />

        <div class="max-w-4xl space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <p class="flex-1 text-sm text-muted">
                    <span
                        v-for="(count, key) in counts"
                        :key="key"
                    >
                        {{ count }} {{ STATUS[key as keyof typeof STATUS]?.label.toLowerCase() ?? key }}<span class="text-dimmed"> · </span>
                    </span>
                    <span class="text-dimmed">nothing here was bought.</span>
                </p>

                <USelect
                    v-model="status"
                    :items="FILTERS"
                    class="w-56"
                />
            </div>

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

            <UAlert
                v-if="filters.company"
                color="neutral"
                variant="subtle"
                icon="i-lucide-filter"
                title="Showing one company"
                :actions="[{ label: 'Show all', color: 'neutral', variant: 'outline', onClick: () => router.get(contactRoutes.index.url()) }]"
            />

            <p
                v-if="!contacts.data.length"
                class="text-sm text-muted"
            >
                Nobody yet. Find contacts from the Companies tab — half of small
                local businesses publish a phone number and no address at all,
                so an empty answer there is a finding, not a failure.
            </p>

            <div
                v-for="contact in contacts.data"
                :key="contact.id"
                class="flex items-start gap-3 rounded-lg p-4 ring ring-default"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">
                        {{ contact.name ?? contact.email ?? 'Unnamed' }}
                        <span
                            v-if="contact.title"
                            class="font-normal text-muted"
                        >— {{ contact.title }}</span>
                    </p>

                    <p class="truncate text-sm text-muted">
                        <ULink
                            v-if="contact.email"
                            :href="`mailto:${contact.email}`"
                        >{{ contact.email }}</ULink>
                        <span v-else>No address</span>

                        <span v-if="contact.company"> · {{ contact.company.name }}</span>
                        <span v-if="contact.company?.location"> · {{ contact.company.location }}</span>
                    </p>

                    <p
                        v-if="contact.email_source"
                        class="text-xs text-dimmed"
                    >
                        {{ SOURCE[contact.email_source] }}<span v-if="contact.email_status">. {{ STATUS[contact.email_status].help }}</span>
                    </p>
                </div>

                <ULink
                    v-if="contact.source_url"
                    :href="contact.source_url"
                    target="_blank"
                    rel="noopener"
                    class="shrink-0 text-sm text-muted"
                >
                    Where we found this
                </ULink>

                <UBadge
                    v-if="contact.email_status"
                    :color="STATUS[contact.email_status].color"
                    variant="subtle"
                    :label="STATUS[contact.email_status].label"
                />

                <!-- Imported rows arrive with no verdict: verifying at import
                     would be a DNS lookup and an SMTP probe per line, with
                     somebody watching a spinner. -->
                <UBadge
                    v-else-if="contact.email"
                    color="neutral"
                    variant="outline"
                    label="Not checked"
                />
            </div>

            <div
                v-if="contacts.meta.last_page > 1"
                class="flex justify-center"
            >
                <UPagination
                    :default-page="contacts.meta.current_page"
                    :items-per-page="contacts.meta.per_page"
                    :total="contacts.meta.total"
                    @update:page="page => router.get(contactRoutes.index.url(), { ...filters, page }, { preserveState: true })"
                />
            </div>
        </div>
    </LeadsLayout>
</template>
