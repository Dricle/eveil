<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import MailboxForm from '@/components/MailboxForm.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import mailboxRoutes from '@/routes/settings/mailboxes'
import type { Mailbox, Project } from '@/types'

defineOptions({ layout: [AppLayout, [SettingsLayout, { title: 'Mailboxes' }]] })

// Inline rather than a type alias imported through the barrel: an alias there
// silently declares no props at all.
defineProps<{
    mailboxes: Mailbox[]
    projects: Project[]
    redirectTo: string | null
}>()

const page = usePage()

// One form, opened either empty or on an existing mailbox. Editing in place
// would mean a form per row and a password field per row, when the whole screen
// is normally used twice: once at setup, once when a password changes.
const editing = ref<Mailbox | null>(null)
const creating = ref(false)
const testing = ref<number | null>(null)
const reactivating = ref<number | null>(null)

function open (mailbox: Mailbox | null) {
    editing.value = mailbox
    creating.value = true
}

function test (mailbox: Mailbox) {
    testing.value = mailbox.id
    router.post(mailboxRoutes.test.url({ project: page.props.currentProject!.slug, mailbox: mailbox.id }), {}, {
        preserveScroll: true,
        onFinish: () => testing.value = null
    })
}

// A pause is a decision the app made, not proof the mailbox is broken: a
// circuit-breaker trip on one bad lead, a password that has since been fixed
// elsewhere. Undoing it does not re-test the connection, that is what "Test"
// is for.
function reactivate (mailbox: Mailbox) {
    reactivating.value = mailbox.id
    router.post(mailboxRoutes.reactivate.url({ project: page.props.currentProject!.slug, mailbox: mailbox.id }), {}, {
        preserveScroll: true,
        onFinish: () => reactivating.value = null
    })
}

const STATUS = {
    active: { color: 'success' as const, label: 'Active' },
    paused: { color: 'warning' as const, label: 'Paused' },
    error: { color: 'error' as const, label: 'Not working' }
}

function verdict (mailbox: Mailbox) {
    return STATUS[mailbox.status]
}
</script>

<template>
    <Head title="Mailboxes" />

    <div class="space-y-4">
        <p class="text-sm text-muted">
            Mail goes out through your own mailbox, over plain SMTP, and
            replies are read back over IMAP. Nothing is relayed through a
            third party, so what arrives is a message from you. A mailbox
            belongs to your organization; each project you tick below may
            send through it.
        </p>

        <!-- Impossible to miss on purpose: every mail going to one address
             looks like outreach working until you check the recipient. -->
        <UAlert
            v-if="redirectTo"
            color="warning"
            variant="subtle"
            icon="i-lucide-flask-conical"
            title="Test mode: every outreach mail goes to one address"
            :description="`OUTREACH_REDIRECT_TO is set to ${redirectTo}, so nothing reaches a prospect. The sender, the SMTP connection and the thread are real. Only the recipient is replaced, and the intended one is in the subject. Replies you write still come back to the mailbox below.`"
        />

        <UAlert
            v-if="page.props.status"
            color="success"
            variant="subtle"
            icon="i-lucide-check"
            :description="String(page.props.status)"
        />

        <!-- The reason a connection test exists: the sentence names what to
             change, and half the time it is a provider policy rather than a
             typo. -->
        <UAlert
            v-if="page.props.errors?.test"
            color="error"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="That mailbox did not answer"
            :description="String(page.props.errors.test)"
        />

        <div
            v-for="mailbox in mailboxes"
            :key="mailbox.id"
            class="space-y-2 rounded-lg p-4 ring ring-default"
        >
            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="font-medium">
                        {{ mailbox.from_name }} &lt;{{ mailbox.from_email }}&gt;
                    </p>
                    <p class="text-sm text-muted">
                        {{ mailbox.smtp_host }}:{{ mailbox.smtp_port }} · {{ mailbox.imap_host }}:{{ mailbox.imap_port }}
                    </p>
                </div>

                <UBadge
                    :color="verdict(mailbox).color"
                    variant="subtle"
                    :label="verdict(mailbox).label"
                />

                <!-- A real message, to this address itself. Anything short
                     of that misses a provider that refuses the from address,
                     which it only says after the body is on the wire. -->
                <UButton
                    icon="i-lucide-plug"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    label="Test"
                    title="Sends a test message to this address"
                    :loading="testing === mailbox.id"
                    @click="test(mailbox)"
                />

                <UButton
                    v-if="mailbox.status !== 'active'"
                    icon="i-lucide-play"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    label="Reactivate"
                    title="Resumes sending without re-testing the connection"
                    :loading="reactivating === mailbox.id"
                    @click="reactivate(mailbox)"
                />

                <UButton
                    icon="i-lucide-pencil"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    label="Edit"
                    @click="open(mailbox)"
                />
            </div>

            <!-- The quota belongs to the address, so this is today's total
                 across every project that shares it. -->
            <p class="text-sm text-dimmed">
                {{ mailbox.sent_today }} of {{ mailbox.allowance_today }} sent today<span v-if="mailbox.ramping_up"> · ramping up towards {{ mailbox.daily_limit }}</span>
                <span v-if="mailbox.projects.length === 0"> · no project may send through it yet</span>
            </p>

            <p
                v-if="mailbox.last_error"
                class="text-sm text-error"
            >
                {{ mailbox.last_error }}
            </p>
        </div>

        <p
            v-if="!mailboxes.length"
            class="rounded-lg p-6 text-sm text-muted ring ring-default"
        >
            No mailbox yet. Nothing can be sent until one is connected and a
            project is allowed to use it.
        </p>

        <UButton
            icon="i-lucide-plus"
            label="Connect a mailbox"
            @click="open(null)"
        />
    </div>

    <MailboxForm
        v-model:open="creating"
        :mailbox="editing"
        :projects="projects"
    />
</template>
