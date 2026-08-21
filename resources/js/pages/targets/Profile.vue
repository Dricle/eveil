<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import TargetHeader from '@/components/TargetHeader.vue'
import TargetsLayout from '@/layouts/TargetsLayout.vue'
import discoveryRuns from '@/routes/discovery-runs'
import targets from '@/routes/targets'
import type { TargetProfile } from '@/types'

const props = defineProps<{ profile: TargetProfile | null }>()

const TEXTS = [
    { name: 'rationale', label: 'Why they buy', help: 'What makes this segment want the product.' },
    { name: 'company_size', label: 'Company size', help: 'Headcount, locations or revenue, whatever is visible from outside.' },
    { name: 'estimated_market_size', label: 'How many there are', help: 'Rough count, and how it was arrived at.' }
] as const

const LISTS = [
    { name: 'sectors', label: 'Sectors', help: 'Lines of business, named the way a directory names them.' },
    { name: 'geography', label: 'Geography', help: 'Countries, regions or cities.' },
    { name: 'job_titles', label: 'Job titles', help: 'Who signs. For a small business, usually the owner.' },
    { name: 'technologies', label: 'Technologies', help: 'Tools these companies visibly use, when that narrows the search.' },
    { name: 'trigger_signals', label: 'Trigger signals', help: 'Observable events meaning now is the moment.' },
    { name: 'search_queries', label: 'Search queries', help: 'What the discovery run actually searches for.' }
] as const

const TYPES = [
    { label: 'Customer, they buy it', value: 'customer' },
    { label: 'Partner, they already reach the buyer', value: 'partner' }
]

// A partner is written to about what the deal does for THEM, so the two angles
// only exist on that kind of profile, and they are what the email opens on.
const ANGLES = [
    { name: 'access_angle', label: 'How they reach the buyer', help: 'How this partner touches the customer, how often, and how many customers one of them carries.' },
    { name: 'partnership_angle', label: 'What is in it for them', help: 'Why the deal is worth their while. This is the opening line of the email, and "buy this" never is.' }
] as const

const type = ref(props.profile?.type ?? 'customer')

// The list fields go over the wire one item per line; the server splits them.
function lines (field: typeof LISTS[number]['name']): string {
    return (props.profile?.criteria?.[field] ?? []).join('\n')
}

// Every field is bound, never left to `default-value`: Nuxt UI reads that prop
// once at mount, and Vue then patches a form element's value against what the
// DOM holds, so every later render writes the frozen first value back over what
// was typed. Saving re-renders this page, and so does switching profile in the
// list beside it, which would otherwise show the previous one's criteria.
const name = ref('')
const draft = ref<Record<string, string>>({})
const active = ref(true)

watch(() => props.profile, (profile) => {
    name.value = profile?.name ?? ''
    type.value = profile?.type ?? 'customer'
    active.value = profile?.is_active ?? true

    draft.value = {
        ...Object.fromEntries([...TEXTS, ...ANGLES].map(field => [field.name, profile?.criteria?.[field.name] ?? ''])),
        ...Object.fromEntries(LISTS.map(field => [field.name, lines(field.name)]))
    }
}, { immediate: true, deep: true })
</script>

<template>
    <TargetsLayout :current="profile?.id">
        <Head :title="profile?.name ?? 'New profile'" />

        <div class="max-w-2xl space-y-4">
            <TargetHeader
                :profile="profile"
                tab="profile"
            />

            <Form
                v-slot="{ errors, processing, recentlySuccessful }"
                v-bind="profile ? targets.update.form(profile.id) : targets.store.form()"
                class="space-y-4"
            >
                <UFormField
                    label="Name"
                    name="name"
                    :error="errors.name"
                >
                    <UInput
                        v-model="name"
                        name="name"
                        required
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    label="Kind"
                    name="type"
                    :error="errors.type"
                >
                    <USelect
                        v-model="type"
                        name="type"
                        :items="TYPES"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    v-for="field in (type === 'partner' ? ANGLES : [])"
                    :key="field.name"
                    :label="field.label"
                    :name="field.name"
                    :help="field.help"
                    :error="errors[field.name]"
                >
                    <UTextarea
                        v-model="draft[field.name]"
                        :name="field.name"
                        :rows="2"
                        autoresize
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    v-for="field in TEXTS"
                    :key="field.name"
                    :label="field.label"
                    :name="field.name"
                    :help="field.help"
                    :error="errors[field.name]"
                >
                    <UTextarea
                        v-model="draft[field.name]"
                        :name="field.name"
                        :rows="2"
                        autoresize
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    v-for="field in LISTS"
                    :key="field.name"
                    :label="field.label"
                    :name="field.name"
                    :help="`${field.help} One per line.`"
                    :error="errors[field.name]"
                >
                    <UTextarea
                        v-model="draft[field.name]"
                        :name="field.name"
                        :rows="3"
                        autoresize
                        class="w-full"
                    />
                </UFormField>

                <UCheckbox
                    v-model="active"
                    name="is_active"
                    label="Search for these companies"
                    description="Every active profile is one more discovery run, and one more budget."
                />

                <div class="flex items-center gap-3">
                    <UButton
                        type="submit"
                        :loading="processing"
                        :label="profile ? 'Save' : 'Add profile'"
                    />
                    <span
                        v-if="recentlySuccessful"
                        class="text-sm text-muted"
                    >Saved.</span>

                    <UButton
                        v-if="profile"
                        class="ms-auto"
                        type="button"
                        color="neutral"
                        variant="subtle"
                        icon="i-lucide-radar"
                        label="Search with this profile"
                        @click="router.post(discoveryRuns.store.url(), { target_profile: profile.id })"
                    />

                    <UButton
                        v-if="profile"
                        type="button"
                        color="error"
                        variant="ghost"
                        icon="i-lucide-trash-2"
                        label="Delete"
                        @click="router.delete(targets.destroy.url(profile.id))"
                    />
                </div>
            </Form>
        </div>
    </TargetsLayout>
</template>
