<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import LeadsLayout from '@/layouts/LeadsLayout.vue'
import StatusSelect from '@/components/StatusSelect.vue'
import { SOURCES, VERIFICATION } from '@/lib/contacts'
import { OUTREACH_STATUSES } from '@/lib/status'
import companyRoutes from '@/routes/companies'
import contactRoutes from '@/routes/contacts'
import type { ContactSheet } from '@/types'
import { CLASSIFICATIONS } from '@/types/inbox'

const props = defineProps<{ contact: ContactSheet }>()

function verification () {
    return props.contact.email_status === null ? null : VERIFICATION[props.contact.email_status]
}

function origin () {
    return props.contact.email_source === null ? null : SOURCES[props.contact.email_source]
}

function when (value: string | null) {
    return value === null ? 'Never' : new Date(value).toLocaleString()
}

function day (value: string | null) {
    return value === null ? 'Never' : new Date(value).toLocaleDateString()
}
</script>

<template>
    <LeadsLayout>
        <Head :title="contact.name ?? contact.email ?? 'Contact'" />

        <div class="max-w-4xl space-y-4">
            <UButton
                icon="i-lucide-arrow-left"
                color="neutral"
                variant="ghost"
                size="xs"
                label="Contacts"
                @click="router.get(contactRoutes.index.url())"
            />

            <div class="space-y-3 rounded-lg p-4 ring ring-default">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-medium">
                            {{ contact.name ?? 'No name' }}
                        </h2>
                        <p class="text-sm text-muted">
                            {{ contact.title ?? 'Role unknown' }}
                            <span v-if="contact.company_detail"> · {{ contact.company_detail.name }}</span>
                        </p>
                    </div>

                    <StatusSelect
                        :status="contact.status"
                        :options="OUTREACH_STATUSES"
                        :url="contactRoutes.status.url(contact.id)"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <ULink
                        v-if="contact.email"
                        :href="`mailto:${contact.email}`"
                    >{{ contact.email }}</ULink>
                    <span
                        v-else
                        class="text-muted"
                    >No address</span>

                    <UBadge
                        v-if="verification()"
                        :color="verification()!.color"
                        variant="subtle"
                        :label="verification()!.label"
                        :title="verification()!.help"
                    />
                    <UBadge
                        v-else-if="contact.email"
                        color="neutral"
                        variant="outline"
                        label="Not checked"
                    />

                    <ULink
                        v-if="contact.linkedin_url"
                        :href="contact.linkedin_url"
                        target="_blank"
                        rel="noopener"
                    >LinkedIn</ULink>
                </div>

                <!-- Provenance, for the user's own audit. It is never put into a
                     mail: no generated notice, no hosted legal text. -->
                <dl class="grid gap-2 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-dimmed">
                            Address came from
                        </dt>
                        <dd>{{ origin() ?? contact.source }}</dd>
                    </div>
                    <div>
                        <dt class="text-dimmed">
                            Found
                        </dt>
                        <dd>{{ day(contact.discovered_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-dimmed">
                            Last written to
                        </dt>
                        <dd>{{ day(contact.last_contacted_at) }}</dd>
                    </div>
                    <div v-if="contact.source_url">
                        <dt class="text-dimmed">
                            Seen on
                        </dt>
                        <dd class="truncate">
                            <ULink
                                :href="contact.source_url"
                                target="_blank"
                                rel="noopener"
                            >{{ contact.source_url }}</ULink>
                        </dd>
                    </div>
                    <div v-if="contact.language">
                        <dt class="text-dimmed">
                            Writes in
                        </dt>
                        <dd>{{ contact.language }}</dd>
                    </div>
                    <div v-if="contact.email_verified_at">
                        <dt class="text-dimmed">
                            Address checked
                        </dt>
                        <dd>{{ day(contact.email_verified_at) }}</dd>
                    </div>
                </dl>
            </div>

            <!-- The company is a deduplicated object of its own, referenced and
                 never copied onto the person: two contacts at one firm must not
                 disagree about what that firm is. -->
            <div
                v-if="contact.company_detail"
                class="space-y-2 rounded-lg p-4 ring ring-default"
            >
                <div class="flex items-center justify-between gap-3">
                    <h3 class="font-medium">
                        {{ contact.company_detail.name }}
                    </h3>

                    <StatusSelect
                        :status="contact.company_detail.status"
                        :options="OUTREACH_STATUSES"
                        :url="companyRoutes.status.url(contact.company_detail.id)"
                    />
                </div>

                <p class="text-sm text-muted">
                    {{ [contact.company_detail.industry, contact.company_detail.size, contact.company_detail.location].filter(Boolean).join(' · ') || 'Nothing recorded yet' }}
                </p>

                <div
                    v-for="(evaluation, index) in contact.company_detail.evaluations"
                    :key="index"
                    class="text-sm"
                >
                    <span class="text-dimmed">{{ evaluation.profile ?? 'Deleted profile' }} · {{ evaluation.fit_score }} · </span>
                    {{ evaluation.fit_reason }}
                </div>
            </div>

            <div class="space-y-2 rounded-lg p-4 ring ring-default">
                <h3 class="font-medium">
                    Sequences
                </h3>

                <p
                    v-if="!contact.campaigns.length"
                    class="text-sm text-muted"
                >
                    Not in any campaign.
                </p>

                <div
                    v-for="membership in contact.campaigns"
                    :key="membership.id"
                    class="flex flex-wrap items-center gap-2 text-sm"
                >
                    <span class="min-w-0 flex-1 truncate">{{ membership.campaign }}</span>
                    <UBadge
                        color="neutral"
                        variant="subtle"
                        :label="membership.pause_reason ? `${membership.status} · ${membership.pause_reason}` : membership.status"
                    />
                    <span class="text-dimmed">step {{ membership.step }}</span>
                    <span
                        v-if="membership.mailbox"
                        class="text-dimmed"
                    >via {{ membership.mailbox }}</span>
                    <span
                        v-if="membership.next_action_at"
                        class="text-dimmed"
                    >next {{ when(membership.next_action_at) }}</span>
                </div>
            </div>

            <div class="space-y-3 rounded-lg p-4 ring ring-default">
                <h3 class="font-medium">
                    Everything either way
                </h3>

                <p
                    v-if="!contact.messages.length"
                    class="text-sm text-muted"
                >
                    Nothing has been sent yet.
                </p>

                <div
                    v-for="message in contact.messages"
                    :key="message.id"
                    class="rounded-lg p-3 text-sm"
                    :class="message.direction === 'inbound' ? 'bg-elevated' : 'ring ring-default'"
                >
                    <p class="mb-1 flex flex-wrap items-center gap-2 text-xs text-dimmed">
                        <span>{{ message.direction === 'inbound' ? 'Them' : 'You' }} · {{ when(message.at) }} · {{ message.subject }}</span>
                        <UBadge
                            v-if="message.classification"
                            color="neutral"
                            variant="subtle"
                            size="sm"
                            :label="CLASSIFICATIONS[message.classification].label"
                        />
                        <UBadge
                            v-if="message.status === 'bounced'"
                            color="error"
                            variant="subtle"
                            size="sm"
                            label="Bounced"
                        />
                    </p>
                    <p class="whitespace-pre-wrap">
                        {{ message.body }}
                    </p>
                </div>
            </div>
        </div>
    </LeadsLayout>
</template>
