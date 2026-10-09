<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import CampaignHeader from '@/components/CampaignHeader.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { relativeUrl } from '@/lib/utils'
import mailboxRoutes from '@/routes/settings/mailboxes'
import type { CampaignLead, CampaignStatus, Pipeline, SendingState } from '@/types'

defineOptions({ layout: AppLayout })

const props = defineProps<{
    campaign: {
        id: number
        name: string
        status: CampaignStatus
        target_profile: { id: number | null, name: string | null, type: string | null } | null
    }
    pipeline: Pipeline
    sending: SendingState
    leads: CampaignLead[]
    leadsTotal: number
}>()

const page = usePage()

// `sending.mailboxes` is every mailbox the PROJECT has, not the ones already
// pinned to this run, so an empty list means there is nothing to send from at
// all. It is the one blocker the user has to leave this screen to fix.
const noMailbox = computed(() => props.sending.mailboxes.length === 0)

// The funnel in the order the work goes, so a gap reads as a gap.
const STAGES = [
    { key: 'pending', label: 'Waiting to start' },
    { key: 'running', label: 'In sequence' },
    { key: 'paused', label: 'Paused' },
    { key: 'completed', label: 'Finished' },
    { key: 'stopped', label: 'Stopped' },
    { key: 'failed', label: 'Failed' }
] as const

function moment (value: string | null): string {
    return value === null ? '' : new Date(value).toLocaleString()
}

// Why nothing is leaving this second, said in the order the scheduler asks the
// questions. An active campaign that is quiet is the normal case at 19:00, and
// a screen that cannot say which reason applies makes it look broken.
const blocker = computed(() => {
    if (props.campaign.status !== 'active') {
        return 'This sequence is not running. Nothing leaves until it is started.'
    }

    // This used to read "nobody is in this sequence, so no mailbox is pinned
    // to it yet", which named the wrong thing: the list is the project's
    // mailboxes, and none of them is a settings problem rather than an
    // enrolment one. People went looking at their leads for it.
    if (noMailbox.value) {
        return 'No mailbox is connected to this project, so nothing can be sent. The sequence is kept as it is and starts by itself once one is connected.'
    }

    if (!props.sending.window_open) {
        return `Outside the sending window (${props.sending.window.start}:00 to ${props.sending.window.end}:00). Nothing leaves before then.`
    }

    if (props.sending.next_action_at === null) {
        return 'Nothing is owed right now. The next mail is scheduled when the current step\'s wait is over.'
    }

    if (new Date(props.sending.next_action_at).getTime() > Date.now()) {
        return `Next mail due at ${moment(props.sending.next_action_at)}.`
    }

    if (props.sending.mailboxes.every(box => box.remaining_today < 1)) {
        return 'Due now, but every mailbox has used its allowance for today.'
    }

    const waiting = props.sending.mailboxes.filter(box => box.ready_at !== null)

    if (waiting.length === props.sending.mailboxes.length) {
        return `Due now, waiting for the gap between two mails from the same address (until ${moment(waiting[0].ready_at)}).`
    }

    return 'Due now. It goes out on the next scheduler tick.'
})
</script>

<template>
    <Head :title="campaign.name" />

    <div class="space-y-6 p-6">
        <CampaignHeader
            :campaign="campaign"
            tab="delivery"
        />

        <!-- Activating the campaign is what puts people into it, so this is
             where the answer to "did anything actually happen" belongs. -->
        <div
            v-if="Object.keys(pipeline).length"
            class="flex flex-wrap gap-4 rounded-lg p-4 text-sm ring ring-default"
        >
            <div
                v-for="stage in STAGES"
                :key="stage.key"
                class="min-w-24"
            >
                <p class="text-dimmed">
                    {{ stage.label }}
                </p>
                <p class="text-lg">
                    {{ pipeline[stage.key] ?? 0 }}
                </p>
            </div>
        </div>

        <p
            v-else-if="campaign.status === 'draft'"
            class="rounded-lg p-4 text-sm text-muted ring ring-default"
        >
            Nobody is in this sequence yet. Starting it enrols the leads
            this project can still write to, and pins a mailbox to each one
            for the whole sequence.
        </p>

        <!-- Started, and still empty. Enrolment refuses to put anybody into a
             sequence with nothing to send from, and returns a plain zero for
             it: on screen that was indistinguishable from having no leads. -->
        <p
            v-else-if="noMailbox"
            class="rounded-lg p-4 text-sm text-muted ring ring-default"
        >
            Nobody is in this sequence, and nobody can be while the project
            has no mailbox. Everyone found since is enrolled as soon as one
            is connected.
        </p>

        <!-- "Active" and "sending right now" are not the same thing, and
             the gap between them is where this screen used to say nothing. -->
        <div class="space-y-3 rounded-lg p-4 ring ring-default">
            <div class="flex items-start gap-3">
                <UIcon
                    :name="campaign.status === 'active' ? 'i-lucide-send' : 'i-lucide-pause'"
                    class="mt-0.5 size-5 shrink-0 text-dimmed"
                />
                <div class="min-w-0">
                    <p class="text-sm">
                        {{ blocker }}
                    </p>
                    <p class="text-sm text-dimmed">
                        Sending is paced: one mail per mailbox per tick, never outside
                        {{ sending.window.start }}:00 to {{ sending.window.end }}:00.
                    </p>

                    <!-- The only blocker on this screen whose fix is on
                         another one. -->
                    <UButton
                        v-if="noMailbox"
                        class="mt-2"
                        color="neutral"
                        variant="subtle"
                        size="sm"
                        icon="i-lucide-mail"
                        label="Connect a mailbox"
                        :to="relativeUrl(mailboxRoutes.index.url({ project: page.props.currentProject!.slug }))"
                    />
                </div>
            </div>

            <div
                v-if="sending.mailboxes.length"
                class="flex flex-wrap gap-4 text-sm"
            >
                <div
                    v-for="mailbox in sending.mailboxes"
                    :key="mailbox.id"
                    class="min-w-56 rounded-lg bg-elevated p-3"
                >
                    <p class="truncate font-medium">
                        {{ mailbox.from_email }}
                    </p>
                    <p class="text-muted">
                        {{ mailbox.sent_today }} sent today of {{ mailbox.allowance_today }},
                        {{ mailbox.remaining_today }} left
                    </p>
                    <p
                        v-if="mailbox.status !== 'active'"
                        class="text-warning"
                    >
                        Mailbox {{ mailbox.status }}: nothing leaves it until that is fixed.
                    </p>
                    <p
                        v-else-if="mailbox.ready_at"
                        class="text-dimmed"
                    >
                        Next send from this address after {{ moment(mailbox.ready_at) }}.
                    </p>
                </div>
            </div>
        </div>

        <!-- Who is where, ordered by what is owed first. The whole list
             belongs on Contacts; this is the run at a glance. -->
        <div
            v-if="leads.length"
            class="space-y-2 rounded-lg p-4 ring ring-default"
        >
            <div class="flex flex-wrap items-baseline gap-2">
                <p class="font-medium">
                    In this sequence
                </p>
                <p
                    v-if="leadsTotal > leads.length"
                    class="text-sm text-dimmed"
                >
                    Showing {{ leads.length }} of {{ leadsTotal }}. The rest are on Contacts.
                </p>
            </div>

            <div
                v-for="lead in leads"
                :key="lead.id"
                class="flex flex-wrap items-center gap-3 border-t border-default py-2 text-sm first:border-0"
            >
                <span class="min-w-48 flex-1 truncate">
                    {{ lead.name ?? lead.email }}
                    <span
                        v-if="lead.company"
                        class="text-dimmed"
                    >· {{ lead.company }}</span>
                </span>

                <UBadge
                    color="neutral"
                    variant="subtle"
                    :label="lead.status"
                />

                <span class="text-muted">
                    {{ lead.last_step === 0 ? 'not started' : `step ${lead.last_step} done` }}
                </span>
                <span class="text-muted">{{ lead.sent ?? 0 }} sent</span>

                <span class="min-w-44 text-dimmed">
                    <template v-if="lead.pause_reason">{{ lead.pause_reason }}</template>
                    <template v-else-if="lead.next_action_at">due {{ moment(lead.next_action_at) }}</template>
                    <template v-else>nothing owed</template>
                </span>
            </div>
        </div>
    </div>
</template>
