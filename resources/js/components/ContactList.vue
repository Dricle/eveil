<script setup lang="ts">
import StatusSelect from '@/components/StatusSelect.vue'
import { OUTREACH_STATUSES } from '@/lib/status'
import { SOURCES, VERIFICATION } from '@/lib/contacts'
import contactRoutes from '@/routes/contacts'
import type { Contact } from '@/types'

// The people at one company, as a compact list rather than the full table: with
// three rows a filter bar and a pager are furniture, not features.
defineProps<{
    contacts: Contact[]
    // Set while a contact search is still reading this company's site, so an
    // empty list does not read as "nobody works here".
    searching?: boolean
}>()

function verdict (contact: Contact) {
    return contact.email_status === null ? null : VERIFICATION[contact.email_status]
}

function origin (contact: Contact) {
    return contact.email_source === null ? null : SOURCES[contact.email_source]
}
</script>

<template>
    <div class="space-y-2">
        <div
            v-for="contact in contacts"
            :key="contact.id"
            class="flex flex-wrap items-center gap-3 rounded-lg p-3 text-sm ring ring-default"
        >
            <div class="min-w-0 flex-1">
                <ULink
                    :href="contactRoutes.show.url(contact.id)"
                    class="font-medium"
                >{{ contact.name ?? contact.email ?? 'No name' }}</ULink>
                <p
                    v-if="contact.title"
                    class="text-muted"
                >
                    {{ contact.title }}
                </p>
            </div>

            <ULink
                v-if="contact.email"
                :href="`mailto:${contact.email}`"
                class="min-w-0 truncate"
            >{{ contact.email }}</ULink>

            <UBadge
                v-if="verdict(contact)"
                :color="verdict(contact)!.color"
                variant="subtle"
                :label="verdict(contact)!.label"
                :title="verdict(contact)!.help"
            />
            <UBadge
                v-else-if="contact.email"
                color="neutral"
                variant="outline"
                label="Not checked"
                title="Imported rows arrive with no verdict. Verifying happens before sending."
            />

            <span
                v-if="origin(contact)"
                class="text-dimmed"
            >{{ origin(contact) }}</span>

            <StatusSelect
                :status="contact.status"
                :options="OUTREACH_STATUSES"
                :url="contactRoutes.status.url(contact.id)"
            />
        </div>

        <!-- Reading a site takes a moment, and an empty list in the meantime
             says something false about the company. -->
        <div
            v-if="searching"
            class="flex items-center gap-3 rounded-lg bg-elevated p-3 text-sm"
        >
            <UIcon
                name="i-lucide-search"
                class="animate-sweep size-4 text-primary"
            />
            <span class="text-muted">Reading their site for people to write to.</span>
        </div>

        <p
            v-else-if="!contacts.length"
            class="rounded-lg p-3 text-sm text-muted ring ring-default"
        >
            Nobody found. Half of small businesses publish a phone number and no
            address at all, so an empty answer here is a finding rather than a
            failure.
        </p>
    </div>
</template>
