<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import ListRows from '@/components/ListRows.vue'
import TagChips from '@/components/TagChips.vue'
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

// Each travels to the server as a real array (`name[]`), not a line-split
// string: `TagChips` and `ListRows` are the two widgets that read/write it.
const LISTS = ['sectors', 'geography', 'job_titles', 'technologies', 'trigger_signals', 'search_queries'] as const

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

// Every field is bound, never left to `default-value`: Nuxt UI reads that prop
// once at mount, and Vue then patches a form element's value against what the
// DOM holds, so every later render writes the frozen first value back over what
// was typed. Saving re-renders this page, and so does switching profile in the
// list beside it, which would otherwise show the previous one's criteria.
const name = ref('')
const draft = ref<Record<string, string>>({})
const draftList = ref<Record<typeof LISTS[number], string[]>>({} as Record<typeof LISTS[number], string[]>)
const active = ref(true)

watch(() => props.profile, (profile) => {
    name.value = profile?.name ?? ''
    type.value = profile?.type ?? 'customer'
    active.value = profile?.is_active ?? true

    draft.value = Object.fromEntries([...TEXTS, ...ANGLES].map(field => [field.name, profile?.criteria?.[field.name] ?? '']))
    draftList.value = Object.fromEntries(LISTS.map(field => [field, profile?.criteria?.[field] ?? []])) as Record<typeof LISTS[number], string[]>
}, { immediate: true, deep: true })
</script>

<template>
    <TargetsLayout :current="profile?.id">
        <Head :title="profile?.name ?? 'New profile'" />

        <div class="space-y-5">
            <TargetHeader
                :profile="profile"
                tab="profile"
            />

            <!-- The model reported low confidence in its own guess: it lands
                 inactive rather than spending budget on its own next tick, and
                 stays this way until a human looks at it. -->
            <UAlert
                v-if="profile?.needs_review"
                color="warning"
                variant="subtle"
                icon="i-lucide-shield-question"
                title="The agent wasn't confident about this one"
                :description="`Scored ${profile.confidence}% confidence, below the floor to search on its own. Review the criteria below and turn it on, or search with it manually, whenever you're satisfied.`"
            />

            <Form
                v-slot="{ errors, processing, recentlySuccessful }"
                v-bind="profile ? targets.update.form(profile.id) : targets.store.form()"
                class="space-y-5"
            >
                <UCard variant="subtle">
                    <template #header>
                        <h3 class="flex items-center gap-2 text-sm font-semibold">
                            <UIcon
                                name="i-lucide-target"
                                class="size-4 text-dimmed"
                            />
                            The segment
                        </h3>
                    </template>

                    <div class="space-y-4">
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

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                                label="Company size"
                                name="company_size"
                                :error="errors.company_size"
                            >
                                <UInput
                                    v-model="draft.company_size"
                                    name="company_size"
                                    class="w-full"
                                />
                            </UFormField>
                        </div>

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
                            label="Why they buy"
                            name="rationale"
                            help="What makes this segment want the product."
                            :error="errors.rationale"
                        >
                            <UTextarea
                                v-model="draft.rationale"
                                name="rationale"
                                :rows="4"
                                autoresize
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            label="How many there are"
                            name="estimated_market_size"
                            help="Rough count, and how it was arrived at."
                            :error="errors.estimated_market_size"
                        >
                            <UTextarea
                                v-model="draft.estimated_market_size"
                                name="estimated_market_size"
                                :rows="3"
                                autoresize
                                class="w-full"
                            />
                        </UFormField>
                    </div>
                </UCard>

                <UCard variant="subtle">
                    <template #header>
                        <h3 class="flex items-center gap-2 text-sm font-semibold">
                            <UIcon
                                name="i-lucide-building-2"
                                class="size-4 text-dimmed"
                            />
                            Who they are
                        </h3>
                    </template>

                    <div class="space-y-4">
                        <UFormField
                            label="Sectors"
                            name="sectors"
                            hint="Enter to add, click a pill to remove"
                            :error="errors.sectors"
                        >
                            <TagChips
                                v-model="draftList.sectors"
                                name="sectors"
                                placeholder="Add a sector…"
                            />
                        </UFormField>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <UFormField
                                label="Geography"
                                name="geography"
                                :error="errors.geography"
                            >
                                <TagChips
                                    v-model="draftList.geography"
                                    name="geography"
                                    placeholder="Add…"
                                />
                            </UFormField>

                            <UFormField
                                label="Job titles"
                                name="job_titles"
                                :error="errors.job_titles"
                            >
                                <TagChips
                                    v-model="draftList.job_titles"
                                    name="job_titles"
                                    placeholder="Add…"
                                />
                            </UFormField>
                        </div>

                        <UFormField
                            label="Technologies"
                            name="technologies"
                            help="Tools these companies visibly use, when that narrows the search."
                            :error="errors.technologies"
                        >
                            <TagChips
                                v-model="draftList.technologies"
                                name="technologies"
                                placeholder="Add…"
                            />
                        </UFormField>
                    </div>
                </UCard>

                <UCard variant="subtle">
                    <template #header>
                        <h3 class="flex items-center gap-2 text-sm font-semibold">
                            <UIcon
                                name="i-lucide-search"
                                class="size-4 text-dimmed"
                            />
                            How the run finds them
                        </h3>
                    </template>

                    <div class="space-y-4">
                        <UFormField
                            label="Search queries"
                            name="search_queries"
                            help="Exactly what the discovery run searches for."
                            :error="errors.search_queries"
                        >
                            <ListRows
                                v-model="draftList.search_queries"
                                name="search_queries"
                                variant="numbered"
                                add-label="add a query…"
                            />
                        </UFormField>

                        <UFormField
                            label="Trigger signals"
                            name="trigger_signals"
                            help="Observable events meaning now is the moment."
                            :error="errors.trigger_signals"
                        >
                            <ListRows
                                v-model="draftList.trigger_signals"
                                name="trigger_signals"
                                variant="bulleted"
                                add-label="add a signal…"
                            />
                        </UFormField>
                    </div>
                </UCard>

                <div class="flex items-start gap-3 rounded-lg bg-elevated p-4">
                    <UCheckbox
                        v-model="active"
                        name="is_active"
                    />
                    <div>
                        <p class="text-sm font-medium text-highlighted">
                            Search for these companies
                        </p>
                        <p class="text-sm text-muted">
                            Every active profile is one more discovery run, and one more budget.
                        </p>
                    </div>
                </div>

                <div class="sticky bottom-0 flex flex-wrap items-center gap-3 bg-gradient-to-t from-default from-70% to-transparent pt-4">
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
