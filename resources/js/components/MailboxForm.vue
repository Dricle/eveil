<script setup lang="ts">
import { Form, router, usePage } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import mailboxRoutes from '@/routes/settings/mailboxes'
import type { Mailbox, Project } from '@/types'
import { PROVIDER_PRESETS } from '@/types/mailbox'

// Inline rather than a type alias imported through the barrel: an alias there
// silently declares no props at all.
const props = withDefaults(defineProps<{
    open: boolean
    /** The mailbox being edited, or null to connect a new one. */
    mailbox?: Mailbox | null
    projects: Project[]
    /** Ticked when connecting a new mailbox, so a screen that is waiting on this project can say so. */
    defaultProjects?: number[]
}>(), {
    mailbox: null,
    defaultProjects: () => []
})

const emit = defineEmits<{ 'update:open': [value: boolean], 'saved': [] }>()

const page = usePage()

const preset = ref<string | undefined>()

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
        projects: [...props.defaultProjects] as number[]
    }
}

// Rebuilt as the form opens rather than when the mailbox prop changes: closing
// and reopening on the same row has to start from the stored values again,
// passwords blank included.
watch(() => props.open, (isOpen) => {
    if (!isOpen) {
        return
    }

    preset.value = undefined
    form.value = props.mailbox === null
        ? blank()
        : {
                ...blank(),
                ...props.mailbox,
                // The columns are nullable, the selects are not: a blank
                // encryption is a plain connection, which these presets never
                // produce and the form has no option for.
                smtp_encryption: props.mailbox.smtp_encryption ?? 'starttls',
                imap_encryption: props.mailbox.imap_encryption ?? 'tls',
                smtp_password: '',
                imap_password: '',
                signature: props.mailbox.signature ?? ''
            }
}, { immediate: true })

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

function note () {
    return PROVIDER_PRESETS.find(item => item.label === preset.value)?.note
}

function saved () {
    emit('update:open', false)
    emit('saved')
}
</script>

<template>
    <UModal
        :open="open"
        :title="mailbox ? 'Edit mailbox' : 'Connect a mailbox'"
        description="SMTP for sending, IMAP for reading replies. No OAuth: a mailbox password, or an app password where the provider requires one."
        :ui="{ content: 'max-w-2xl' }"
        @update:open="emit('update:open', $event)"
    >
        <template #body>
            <Form
                v-slot="{ errors, processing }"
                v-bind="mailbox ? mailboxRoutes.update.form({ project: page.props.currentProject!.slug, mailbox: mailbox.id }) : mailboxRoutes.store.form({ project: page.props.currentProject!.slug })"
                class="space-y-4"
                @success="saved"
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
                        :help="mailbox ? 'Leave blank to keep the stored one.' : undefined"
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
                        :help="mailbox ? 'Leave blank to keep the stored one.' : undefined"
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
                        :label="mailbox ? 'Save' : 'Connect'"
                    />

                    <UButton
                        v-if="mailbox"
                        color="error"
                        variant="ghost"
                        label="Remove"
                        @click="router.delete(mailboxRoutes.destroy.url({ project: page.props.currentProject!.slug, mailbox: mailbox.id }), { onSuccess: () => emit('update:open', false) })"
                    />
                </div>
            </Form>
        </template>
    </UModal>
</template>
