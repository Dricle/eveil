<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import autonomyRoutes from '@/routes/settings/autonomy'

defineOptions({ layout: [AppLayout, [SettingsLayout, { title: 'Autonomy' }]] })

const props = defineProps<{
    emailAutonomyLevel: 'supervised' | 'semi_auto' | 'autonomous'
    linkedinAutonomyLevel: 'supervised' | 'autonomous'
}>()

const page = usePage()
const toast = useToast()

const form = useForm({
    email_autonomy_level: props.emailAutonomyLevel,
    linkedin_autonomy_level: props.linkedinAutonomyLevel
})

// Same words as `docs/product/autonomy.md`: the two places this is
// explained must not drift apart.
const EMAIL = [
    {
        value: 'supervised',
        label: 'Supervised',
        description: 'Nothing happens until you click. You start every search, say yes or no to each company, and start every campaign yourself.'
    },
    {
        value: 'semi_auto',
        label: 'Semi-auto',
        description: 'You approve companies, Eveil emails the people there by itself after that.'
    },
    {
        value: 'autonomous',
        label: 'Autonomous',
        description: 'Eveil does everything, you just read the replies. It emails every company it finds that looks like a good fit, and writes the campaigns itself.'
    }
]

const LINKEDIN = [
    {
        value: 'supervised',
        label: 'Supervised',
        description: 'Every post waits for you to approve it.'
    },
    {
        value: 'autonomous',
        label: 'Autonomous',
        description: 'Posts are published as soon as they are written. A post naming a client, or a project with several LinkedIn accounts, still waits for you.'
    }
]

function save () {
    form.put(autonomyRoutes.update.url({ project: page.props.currentProject!.slug }), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Autonomy saved', color: 'success' })
    })
}
</script>

<template>
    <Head title="Autonomy" />

    <div class="space-y-4">
        <UCard>
            <template #header>
                <h2 class="font-medium">
                    Emails
                </h2>
                <p class="mt-1 text-sm text-muted">
                    Emails only ever go out from mailboxes you connected, and
                    anything you set aside stays set aside, whatever you pick.
                </p>
            </template>

            <UFormField :error="form.errors.email_autonomy_level">
                <URadioGroup
                    v-model="form.email_autonomy_level"
                    :items="EMAIL"
                />
            </UFormField>
        </UCard>

        <UCard>
            <template #header>
                <h2 class="font-medium">
                    LinkedIn
                </h2>
            </template>

            <UFormField :error="form.errors.linkedin_autonomy_level">
                <URadioGroup
                    v-model="form.linkedin_autonomy_level"
                    :items="LINKEDIN"
                />
            </UFormField>
        </UCard>

        <UCard>
            <template #header>
                <h2 class="font-medium">
                    Reddit
                </h2>
            </template>

            <p class="text-sm text-muted">
                Always supervised. Eveil drafts replies, you post them yourself:
                posting on its own could get your account banned.
            </p>
        </UCard>

        <UButton
            label="Save"
            :loading="form.processing"
            @click="save"
        />
    </div>
</template>
