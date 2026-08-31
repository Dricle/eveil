<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import mailboxRoutes from '@/routes/settings/mailboxes'
import type { Mailbox, Project } from '@/types'
import { PROVIDER_PRESETS } from '@/types/mailbox'

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
const preset = ref<string | undefined>()
const testing = ref<number | null>(null)
const reactivating = ref<number | null>(null)

// Prefilled from the preset so nobody has to find their provider's host names,
// which is where most of the abandonment happens.
const form = ref(blank())

function blank () {
    return {
        name: '',
        from_name: '',
        from_email: '',
        smtp_host: '',
        smtp_port: 587,
        smtp_username: '',
        smtp_password: '',
        smtp_encryption: 'starttls',
        imap_host: '',
        imap_port: 993,
        imap_username: '',
        imap_password: '',
        imap_encryption: 'tls',
        signature: '',
        daily_limit: 30,
        max_bounce_rate: null as number | null,
        projects: [] as number[]
    }
}

function open (mailbox: Mailbox | null) {
    editing.value = mailbox
    preset.value = undefined
    form.value = mailbox === null
        ? blank()
        : {
                ...blank(),
                ...mailbox,
                // The columns are nullable, the selects are not: a blank
                // encryption is a plain connection, which these presets never
                // produce and the form has no option for.
                smtp_encryption: mailbox.smtp_encryption ?? 'starttls',
                imap_encryption: mailbox.imap_encryption ?? 'tls',
                smtp_password: '',
                imap_password: '',
                signature: mailbox.signature ?? ''
            }
    creating.value = true
}

function applyPreset (label: string) {
    const chosen = PROVIDER_PRESETS.find(item => item.label === label)

    if (!chosen) {
        return
    }

    // Only the connection details: the label and the note belong to the screen,
    // not to the mailbox being saved.
    form.value = {
        ...form.value,
        smtp_host: chosen.smtp_host,
        smtp_port: chosen.smtp_port,
        smtp_encryption: chosen.smtp_encryption,
        imap_host: chosen.imap_host,
        imap_port: chosen.imap_port,
        imap_encryption: chosen.imap_encryption
    }
}

const PRESET_OPTIONS: { label: string, value: string }[] = PROVIDER_PRESETS.map(item => ({
    label: item.label,
    value: item.label
}))

function test (mailbox: Mailbox) {
    testing.value = mailbox.id
    router.post(mailboxRoutes.test.url(mailbox.id), {}, {
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
    router.post(mailboxRoutes.reactivate.url(mailbox.id), {}, {
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

function note () {
    return PROVIDER_PRESETS.find(item => item.label === preset.value)?.note
}
</script>

<template>
    <SettingsLayout title="Mailboxes">
        <Head title="Mailboxes" />

        <div class="max-w-3xl space-y-4">
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
                    <span> · pauses past {{ Math.round(mailbox.effective_max_bounce_rate * 100) }}% bounced<template v-if="mailbox.max_bounce_rate === null"> (instance default)</template></span>
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

        <UModal
            v-model:open="creating"
            :title="editing ? 'Edit mailbox' : 'Connect a mailbox'"
            description="SMTP for sending, IMAP for reading replies. No OAuth: a mailbox password, or an app password where the provider requires one."
            :ui="{ content: 'max-w-2xl' }"
        >
            <template #body>
                <Form
                    v-slot="{ errors, processing }"
                    v-bind="editing ? mailboxRoutes.update.form(editing.id) : mailboxRoutes.store.form()"
                    class="space-y-4"
                    @success="creating = false"
                >
                    <UFormField
                        label="Provider"
                        name="preset"
                        help="Fills in the host names and ports. Skip it if your mail is somewhere else."
                    >
                        <USelect
                            v-model="preset"
                            :items="PRESET_OPTIONS"
                            placeholder="Pick one, or fill it in by hand"
                            class="w-full"
                            @update:model-value="applyPreset"
                        />
                    </UFormField>

                    <UAlert
                        v-if="note()"
                        color="neutral"
                        variant="subtle"
                        icon="i-lucide-info"
                        :description="note()"
                    />

                    <div class="grid gap-3 sm:grid-cols-2">
                        <UFormField
                            label="Name"
                            name="name"
                            :error="errors.name"
                        >
                            <UInput
                                v-model="form.name"
                                name="name"
                                placeholder="Contact"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            label="Daily limit"
                            name="daily_limit"
                            :error="errors.daily_limit"
                            help="What one mailbox may send in a day, across every project."
                        >
                            <UInput
                                v-model="form.daily_limit"
                                name="daily_limit"
                                type="number"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            label="Bounce rate that pauses this mailbox"
                            name="max_bounce_rate"
                            :error="errors.max_bounce_rate"
                        >
                            <UInput
                                v-model="form.max_bounce_rate"
                                name="max_bounce_rate"
                                type="number"
                                min="0.01"
                                max="1"
                                step="0.01"
                                placeholder="Instance default"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            label="From name"
                            name="from_name"
                            :error="errors.from_name"
                        >
                            <UInput
                                v-model="form.from_name"
                                name="from_name"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            label="From address"
                            name="from_email"
                            :error="errors.from_email"
                        >
                            <UInput
                                v-model="form.from_email"
                                name="from_email"
                                type="email"
                                class="w-full"
                            />
                        </UFormField>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-4">
                        <UFormField
                            class="sm:col-span-2"
                            label="SMTP host"
                            name="smtp_host"
                            :error="errors.smtp_host"
                        >
                            <UInput
                                v-model="form.smtp_host"
                                name="smtp_host"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            label="Port"
                            name="smtp_port"
                            :error="errors.smtp_port"
                        >
                            <UInput
                                v-model="form.smtp_port"
                                name="smtp_port"
                                type="number"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            label="Encryption"
                            name="smtp_encryption"
                            :error="errors.smtp_encryption"
                        >
                            <USelect
                                v-model="form.smtp_encryption"
                                name="smtp_encryption"
                                :items="[{ label: 'STARTTLS', value: 'starttls' }, { label: 'TLS', value: 'tls' }]"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            class="sm:col-span-2"
                            label="SMTP username"
                            name="smtp_username"
                            :error="errors.smtp_username"
                            help="Usually the full address."
                        >
                            <UInput
                                v-model="form.smtp_username"
                                name="smtp_username"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            class="sm:col-span-2"
                            label="SMTP password"
                            name="smtp_password"
                            :error="errors.smtp_password"
                            :help="editing ? 'Leave blank to keep the stored one.' : undefined"
                        >
                            <UInput
                                v-model="form.smtp_password"
                                name="smtp_password"
                                type="password"
                                class="w-full"
                            />
                        </UFormField>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-4">
                        <UFormField
                            class="sm:col-span-2"
                            label="IMAP host"
                            name="imap_host"
                            :error="errors.imap_host"
                        >
                            <UInput
                                v-model="form.imap_host"
                                name="imap_host"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            label="Port"
                            name="imap_port"
                            :error="errors.imap_port"
                        >
                            <UInput
                                v-model="form.imap_port"
                                name="imap_port"
                                type="number"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            label="Encryption"
                            name="imap_encryption"
                            :error="errors.imap_encryption"
                        >
                            <USelect
                                v-model="form.imap_encryption"
                                name="imap_encryption"
                                :items="[{ label: 'TLS', value: 'tls' }, { label: 'STARTTLS', value: 'starttls' }]"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            class="sm:col-span-2"
                            label="IMAP username"
                            name="imap_username"
                            :error="errors.imap_username"
                        >
                            <UInput
                                v-model="form.imap_username"
                                name="imap_username"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            class="sm:col-span-2"
                            label="IMAP password"
                            name="imap_password"
                            :error="errors.imap_password"
                            :help="editing ? 'Leave blank to keep the stored one.' : undefined"
                        >
                            <UInput
                                v-model="form.imap_password"
                                name="imap_password"
                                type="password"
                                class="w-full"
                            />
                        </UFormField>
                    </div>

                    <UFormField
                        label="Signature"
                        name="signature"
                        :error="errors.signature"
                        help="The only trailing block a mail carries. Plain text, no links to anything but your own site."
                    >
                        <UTextarea
                            v-model="form.signature"
                            name="signature"
                            :rows="3"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        label="Projects allowed to send through it"
                        name="projects"
                        help="A project with no mailbox cannot send at all, which is the safe default for one you have just created."
                    >
                        <!-- A group, not a list of checkboxes: a lone UCheckbox
                             is boolean and ignores an array model, so every box
                             read back as unchecked when editing. -->
                        <UCheckboxGroup
                            v-model="form.projects"
                            :items="projects"
                            value-key="id"
                            label-key="name"
                        />
                    </UFormField>

                    <!-- The checkbox group posts nothing when every box is
                         cleared, and "no project" has to be sendable as a state. -->
                    <input
                        v-for="id in form.projects"
                        :key="id"
                        type="hidden"
                        name="projects[]"
                        :value="id"
                    >

                    <div class="flex items-center justify-between gap-3">
                        <UButton
                            type="submit"
                            :loading="processing"
                            :label="editing ? 'Save' : 'Connect'"
                        />

                        <UButton
                            v-if="editing"
                            color="error"
                            variant="ghost"
                            label="Remove"
                            @click="router.delete(mailboxRoutes.destroy.url(editing.id), { onSuccess: () => creating = false })"
                        />
                    </div>
                </Form>
            </template>
        </UModal>
    </SettingsLayout>
</template>
