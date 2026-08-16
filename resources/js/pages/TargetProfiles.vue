<script setup lang="ts">
import { Form, Head, router, usePoll } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import targetProfiles from '@/routes/target-profiles'
import type { TargetProfile } from '@/types'

const props = defineProps<{
    profiles: TargetProfile[]
    analyzed: boolean
    deriving: boolean
    derivationError: string | null
}>()

// Only while the agent is working: the profiles land in one go at the end, so
// there is nothing to watch once it is done.
const poll = usePoll(3000, {
    only: ['profiles', 'deriving', 'derivationError']
}, { autoStart: props.deriving })

watch(() => props.deriving, deriving => deriving ? poll.start() : poll.stop())

const TEXTS = [
    { name: 'rationale', label: 'Why they buy', help: 'What makes this segment want the product.' },
    { name: 'company_size', label: 'Company size', help: 'Headcount, locations or revenue — whatever is visible from outside.' },
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
    { label: 'Customer — they buy it', value: 'customer' },
    { label: 'Partner — they already reach the buyer', value: 'partner' }
]

// The blank trailing row is the create form: the fields are the same, only the
// action differs, so there is no second copy of them to keep in step.
const rows = computed<(TargetProfile | null)[]>(() => [...props.profiles, null])

function lines (profile: TargetProfile | null, field: typeof LISTS[number]['name']): string {
    return (profile?.criteria?.[field] ?? []).join('\n')
}

function action (profile: TargetProfile | null) {
    return profile
        ? targetProfiles.update.form(profile.id)
        : targetProfiles.store.form()
}
</script>

<template>
    <AppLayout>
        <template #header>
            <h1 class="font-medium">
                Targets
            </h1>
        </template>

        <Head title="Targets" />

        <div class="max-w-2xl space-y-4 p-4">
            <UAlert
                v-if="!analyzed"
                color="neutral"
                variant="subtle"
                icon="i-lucide-loader"
                title="No product portrait yet"
                description="Profiles are worked out from the site. They can be written by hand in the meantime."
            />

            <div class="flex items-start justify-between gap-4">
                <p class="text-sm text-muted">
                    Who the search goes after. The agent writes these from your
                    product; correcting one keeps it from being rewritten.
                </p>

                <UButton
                    v-if="analyzed"
                    icon="i-lucide-sparkles"
                    color="neutral"
                    variant="subtle"
                    :loading="deriving"
                    :disabled="deriving"
                    :label="deriving
                        ? 'Reading your product…'
                        : profiles.length ? 'Derive again' : 'Derive from my product'"
                    @click="router.post(targetProfiles.derive.url(), {}, { preserveScroll: true })"
                />
            </div>

            <UAlert
                v-if="deriving"
                color="neutral"
                variant="subtle"
                icon="i-lucide-loader"
                :ui="{ icon: 'animate-spin' }"
                title="Working out who to go after"
                description="A minute or two. The profiles appear here on their own — no need to reload."
            />

            <UAlert
                v-else-if="derivationError"
                color="error"
                variant="subtle"
                icon="i-lucide-triangle-alert"
                title="The derivation failed"
                :description="derivationError"
            />

            <!-- ponytail: no per-step progress — the agent returns everything at
                 once, so there is nothing finer to show until the discovery run
                 screen builds the job graph. -->
            <UCollapsible
                v-for="profile in rows"
                :key="profile?.id ?? 'new'"
                class="rounded-lg ring ring-default"
                :default-open="!profile && profiles.length === 0"
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-2 p-4 text-start"
                >
                    <UIcon
                        :name="profile ? 'i-lucide-crosshair' : 'i-lucide-plus'"
                        class="shrink-0 text-dimmed"
                    />

                    <span class="min-w-0 flex-1 truncate font-medium">
                        {{ profile?.name ?? 'New profile' }}
                    </span>

                    <UBadge
                        v-if="profile?.type === 'partner'"
                        color="primary"
                        variant="subtle"
                        label="Partner"
                    />
                    <UBadge
                        v-if="profile && !profile.is_active"
                        color="neutral"
                        variant="subtle"
                        label="Paused"
                    />
                    <UBadge
                        v-if="profile?.source === 'human'"
                        color="neutral"
                        variant="subtle"
                        icon="i-lucide-pencil"
                        label="Edited by you"
                    />
                </button>

                <template #content>
                    <Form
                        v-slot="{ errors, processing, recentlySuccessful }"
                        v-bind="action(profile)"
                        class="space-y-4 border-t border-default p-4"
                    >
                        <UFormField
                            label="Name"
                            name="name"
                            :error="errors.name"
                        >
                            <UInput
                                name="name"
                                :default-value="profile?.name"
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
                                name="type"
                                :items="TYPES"
                                :default-value="profile?.type ?? 'customer'"
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
                                :name="field.name"
                                :default-value="profile?.criteria?.[field.name]"
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
                                :name="field.name"
                                :default-value="lines(profile, field.name)"
                                :rows="3"
                                autoresize
                                class="w-full"
                            />
                        </UFormField>

                        <UCheckbox
                            name="is_active"
                            :default-value="profile?.is_active ?? true"
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
                                color="error"
                                variant="ghost"
                                icon="i-lucide-trash-2"
                                label="Delete"
                                @click="router.delete(targetProfiles.destroy.url(profile.id))"
                            />
                        </div>
                    </Form>
                </template>
            </UCollapsible>
        </div>
    </AppLayout>
</template>
